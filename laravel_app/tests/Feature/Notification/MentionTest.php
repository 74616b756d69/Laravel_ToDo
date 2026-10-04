<?php

namespace Tests\Feature\Notification;

use App\Enums\ProjectRole;
use App\Enums\StatusCategory;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Notifications\CommentPostedNotification;
use App\Notifications\MentionedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * @メンション。
 *
 * 通知が届くことに加えて、HTML を書き換えて送られても
 * 部外者に通知が飛ばない・別人の名前を名乗れないことを確かめる。
 */
class MentionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private User $outsider;

    private Project $project;

    private Issue $issue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => '管理者']);
        $this->member = User::factory()->create(['name' => 'メンバー']);
        $this->outsider = User::factory()->create(['name' => '部外者']);
        $this->project = Project::personalFor($this->owner);
        $this->project->members()->create(['user_id' => $this->member->id, 'role' => ProjectRole::Member]);

        $this->issue = Issue::factory()
            ->inProject($this->project, $this->owner)
            ->inCategory(StatusCategory::Todo)
            ->create();
    }

    private function mention(User $user, ?string $label = null): string
    {
        $label ??= $user->name;

        return "<span data-type=\"mention\" data-id=\"{$user->id}\" data-label=\"{$label}\">@{$label}</span>";
    }

    public function test_コメントでメンションされた人に画面とメールで届く(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)->post(route('comments.store', $this->issue), [
            'body' => '<p>'.$this->mention($this->member).' 確認お願いします</p>',
        ]);

        Notification::assertSentTo(
            $this->member,
            MentionedNotification::class,
            fn (MentionedNotification $notification, array $channels) => $channels === ['database', 'mail']
                && $notification->payload['kind'] === 'mentioned'
                && $notification->payload['message'] === '管理者さんがコメントであなたをメンションしました。'
                && str_contains($notification->payload['excerpt'], '確認お願いします'),
        );
    }

    public function test_メンションされたウォッチャーにはコメント通知を二重に送らない(): void
    {
        Notification::fake();
        $this->issue->watch($this->member);

        $this->actingAs($this->owner)->post(route('comments.store', $this->issue), [
            'body' => '<p>'.$this->mention($this->member).' 見てください</p>',
        ]);

        Notification::assertSentTo($this->member, MentionedNotification::class);
        Notification::assertNotSentTo($this->member, CommentPostedNotification::class);
    }

    public function test_自分をメンションしても届かない(): void
    {
        Notification::fake();

        $this->actingAs($this->member)->post(route('comments.store', $this->issue), [
            'body' => '<p>'.$this->mention($this->member).' メモ</p>',
        ]);

        Notification::assertNotSentTo($this->member, MentionedNotification::class);
    }

    public function test_部外者をメンションしても届かず印も外れる(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)->post(route('comments.store', $this->issue), [
            'body' => '<p>'.$this->mention($this->outsider).' さん</p>',
        ]);

        Notification::assertNothingSentTo($this->outsider);

        $body = Comment::sole()->body;
        $this->assertStringNotContainsString('data-type="mention"', $body);
        // 書かれていた文字は残る
        $this->assertStringContainsString('@部外者', $body);
    }

    public function test_表示名を書き換えて送っても本人の名前に直る(): void
    {
        $this->actingAs($this->owner)->post(route('comments.store', $this->issue), [
            'body' => '<p>'.$this->mention($this->member, '社長').'</p>',
        ]);

        $body = Comment::sole()->body;
        $this->assertStringContainsString('data-label="メンバー"', $body);
        $this->assertStringContainsString('@メンバー', $body);
        $this->assertStringNotContainsString('社長', $body);
    }

    public function test_コメントを編集して増えた人にだけ届く(): void
    {
        $third = User::factory()->create(['name' => '三人目']);
        $this->project->members()->create(['user_id' => $third->id, 'role' => ProjectRole::Member]);

        $this->actingAs($this->owner)->post(route('comments.store', $this->issue), [
            'body' => '<p>'.$this->mention($this->member).'</p>',
        ]);
        $comment = Comment::sole();

        Notification::fake();

        $this->actingAs($this->owner)->put(route('comments.update', [$this->issue, $comment]), [
            'body' => '<p>'.$this->mention($this->member).' '.$this->mention($third).'</p>',
        ]);

        Notification::assertSentTo($third, MentionedNotification::class);
        Notification::assertNotSentTo($this->member, MentionedNotification::class);
    }

    public function test_説明でメンションされた人に届く(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)->patch(route('tasks.content', $this->issue), [
            'content' => '<p>'.$this->mention($this->member).' に相談</p>',
        ]);

        Notification::assertSentTo(
            $this->member,
            MentionedNotification::class,
            fn (MentionedNotification $notification) => $notification->payload['message'] === '管理者さんが説明であなたをメンションしました。',
        );
    }

    public function test_説明を書き直しても前からいる人には再送しない(): void
    {
        $this->actingAs($this->owner)->patch(route('tasks.content', $this->issue), [
            'content' => '<p>'.$this->mention($this->member).' に相談</p>',
        ]);

        Notification::fake();

        $this->actingAs($this->owner)->patch(route('tasks.content', $this->issue), [
            'content' => '<p>'.$this->mention($this->member).' に相談済み</p>',
        ]);

        Notification::assertNothingSent();
    }

    public function test_起票時の説明のメンションも届く(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)->post(route('tasks.store'), [
            'title' => '相談',
            'status' => $this->project->initialStatus()->id,
            'priority' => 'medium',
            'content' => '<p>'.$this->mention($this->member).'</p>',
        ]);

        Notification::assertSentTo($this->member, MentionedNotification::class);
    }

    public function test_課題画面にメンション候補としてメンバーだけが渡る(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tasks.show', $this->issue))
            ->assertOk()
            ->assertSee('data-mention-candidates', false)
            ->assertSee('メンバー')
            ->assertDontSee('部外者');
    }
}
