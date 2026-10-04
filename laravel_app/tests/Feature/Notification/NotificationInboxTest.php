<?php

namespace Tests\Feature\Notification;

use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 通知一覧と既読処理。通知は本物の database チャネルで書いて確かめる。
 */
class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Issue $issue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => '管理者']);
        $this->member = User::factory()->create(['name' => 'メンバー']);
        $project = Project::personalFor($this->owner);
        $project->members()->create(['user_id' => $this->member->id, 'role' => ProjectRole::Member]);

        $this->issue = Issue::factory()->inProject($project, $this->owner)->create(['title' => '請求書を送る']);

        // 管理者がメンバーを担当にする → メンバーに 1 件届く
        $this->actingAs($this->owner)
            ->patch(route('tasks.assignee', $this->issue), ['assignee' => $this->member->id]);
    }

    public function test_一覧に自分宛ての通知が出てヘッダーに未読数が出る(): void
    {
        $this->actingAs($this->member)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('請求書を送る')
            ->assertSee('管理者さんがあなたを担当者にしました。')
            ->assertSee('通知（未読 1 件）');
    }

    public function test_通知が無ければ空の案内を出す(): void
    {
        $this->actingAs($this->owner)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('通知はありません');
    }

    public function test_開くと既読になり課題へ送られる(): void
    {
        $notification = $this->member->notifications()->sole();

        $this->actingAs($this->member)
            ->get(route('notifications.show', $notification->id))
            ->assertRedirect(route('tasks.legacy', $this->issue->id));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_他人の通知は開けない(): void
    {
        $notification = $this->member->notifications()->sole();

        $this->actingAs($this->owner)
            ->get(route('notifications.show', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_すべて既読にできる(): void
    {
        $this->actingAs($this->owner)
            ->post(route('comments.store', $this->issue), ['body' => '<p>よろしくお願いします。</p>']);
        $this->assertSame(2, $this->member->unreadNotifications()->count());

        $this->actingAs($this->member)
            ->post(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'));

        $this->assertSame(0, $this->member->unreadNotifications()->count());
    }

    public function test_ログインしていなければ見られない(): void
    {
        auth()->logout();

        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }
}
