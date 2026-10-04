<?php

namespace Tests\Feature\Notification;

use App\Enums\ProjectRole;
use App\Enums\StatusCategory;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Notifications\CommentPostedNotification;
use App\Notifications\IssueAssignedNotification;
use App\Notifications\IssueTransitionedNotification;
use App\Services\IssueAssignmentService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * 課題のイベントから、誰に通知が届くか。
 *
 * 「届くべき人に届く」と同じくらい「届いてはいけない人に届かない」が大事なので、
 * 操作した本人・プロジェクトから外れた人・操作者のいない変更・
 * ロールバックされた変更をそれぞれ確かめる。
 */
class IssueNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Project $project;

    private Issue $issue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => '管理者']);
        $this->member = User::factory()->create(['name' => 'メンバー']);
        $this->project = Project::personalFor($this->owner);
        $this->project->members()->create(['user_id' => $this->member->id, 'role' => ProjectRole::Member]);

        // ファクトリからの作成は操作者がいないので、ここでは通知は飛ばない
        $this->issue = Issue::factory()
            ->inProject($this->project, $this->owner)
            ->inCategory(StatusCategory::Todo)
            ->create();
    }

    private function named(string $name)
    {
        return $this->project->statuses()->where('name', $name)->sole();
    }

    // --- 担当 ---------------------------------------------------------------

    public function test_担当者にされた本人に画面とメールで届く(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)
            ->patch(route('tasks.assignee', $this->issue), ['assignee' => $this->member->id]);

        Notification::assertSentTo(
            $this->member,
            IssueAssignedNotification::class,
            fn (IssueAssignedNotification $notification, array $channels) => $channels === ['database', 'mail']
                && $notification->payload['issue_key'] === $this->issue->key()
                && $notification->payload['message'] === '管理者さんがあなたを担当者にしました。',
        );
        Notification::assertNotSentTo($this->owner, IssueAssignedNotification::class);
    }

    public function test_自分で自分を担当者にしても届かない(): void
    {
        Notification::fake();

        $this->actingAs($this->member)
            ->patch(route('tasks.assignee', $this->issue), ['assignee' => $this->member->id]);

        Notification::assertNothingSent();
    }

    public function test_担当者つきで起票されたら担当者に届く(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)->post(route('tasks.store'), [
            'title' => 'レビューをお願いします',
            'status' => $this->named('To Do')->id,
            'priority' => 'medium',
            'assignee' => $this->member->id,
        ]);

        Notification::assertSentTo($this->member, IssueAssignedNotification::class);
        // 起票した本人には何も届かない
        Notification::assertNotSentTo($this->owner, IssueAssignedNotification::class);
    }

    // --- ステータス ---------------------------------------------------------

    public function test_ステータスが変わるとウォッチャーに届き操作した本人には届かない(): void
    {
        Notification::fake();
        $this->issue->watch($this->member);

        $this->actingAs($this->owner)
            ->patch(route('tasks.transition', $this->issue), ['status' => $this->named('In Progress')->id]);

        Notification::assertSentTo(
            $this->member,
            IssueTransitionedNotification::class,
            fn (IssueTransitionedNotification $notification, array $channels) => $channels === ['database']
                && $notification->payload['message'] === '管理者さんがステータスを「To Do」から「In Progress」に変更しました。',
        );
        // 起票者として自動でウォッチしているが、自分の操作なので届かない
        Notification::assertNotSentTo($this->owner, IssueTransitionedNotification::class);
    }

    public function test_ウォッチしていない人には届かない(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)
            ->patch(route('tasks.transition', $this->issue), ['status' => $this->named('In Progress')->id]);

        Notification::assertNotSentTo($this->member, IssueTransitionedNotification::class);
    }

    public function test_プロジェクトから外れた人にはウォッチが残っていても届かない(): void
    {
        Notification::fake();
        $outsider = User::factory()->create();
        $this->issue->watch($outsider);

        $this->actingAs($this->owner)
            ->patch(route('tasks.transition', $this->issue), ['status' => $this->named('In Progress')->id]);

        Notification::assertNotSentTo($outsider, IssueTransitionedNotification::class);
    }

    // --- コメント -----------------------------------------------------------

    public function test_コメントがウォッチャーに届き投稿者は自動でウォッチに加わる(): void
    {
        Notification::fake();

        $this->actingAs($this->member)
            ->post(route('comments.store', $this->issue), ['body' => '<p>確認しました。</p>']);

        // 起票者（管理者）はウォッチしているので届く
        Notification::assertSentTo(
            $this->owner,
            CommentPostedNotification::class,
            fn (CommentPostedNotification $notification) => $notification->payload['excerpt'] === '確認しました。',
        );
        Notification::assertNotSentTo($this->member, CommentPostedNotification::class);
        $this->assertTrue($this->issue->isWatchedBy($this->member));
    }

    // --- 届かない変更 -------------------------------------------------------

    public function test_操作した人のいない変更では通知しない(): void
    {
        Notification::fake();
        $this->issue->watch($this->member);

        // 移行コマンドやシーダーと同じく、ログインしていない状態で動かす
        app(IssueAssignmentService::class)->assign($this->issue, $this->member);
        app(WorkflowService::class)->transition($this->issue->fresh(), $this->named('In Progress'));

        Notification::assertNothingSent();
    }

    public function test_ロールバックされた変更では通知しない(): void
    {
        Notification::fake();
        $this->actingAs($this->owner);

        try {
            DB::transaction(function () {
                app(IssueAssignmentService::class)->assign($this->issue, $this->member);

                throw new RuntimeException('後続の処理で失敗した');
            });
        } catch (RuntimeException) {
            // 想定どおり
        }

        Notification::assertNothingSent();
    }

    // --- 自動ウォッチ -------------------------------------------------------

    public function test_起票者と担当者は自動でウォッチに加わる(): void
    {
        $this->assertTrue($this->issue->isWatchedBy($this->owner));

        $this->actingAs($this->owner)
            ->patch(route('tasks.assignee', $this->issue), ['assignee' => $this->member->id]);

        $this->assertTrue($this->issue->isWatchedBy($this->member));
    }
}
