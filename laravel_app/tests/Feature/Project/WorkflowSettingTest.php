<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Enums\StatusCategory;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * プロジェクト設定からのワークフロー編集。
 *
 * どの操作のあとでも「ステータス 1 つ以上・done 1 つ以上」が
 * 保たれていることを確かめる（initialStatus() / doneStatus() が
 * firstOrFail なので、割ると全画面が落ちる）。
 */
class WorkflowSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->project = Project::factory()->withMember($this->admin)->create(['key' => 'WF']);
    }

    private function named(string $name): Status
    {
        return $this->project->statuses()->where('name', $name)->sole();
    }

    // --- ステータス ----------------------------------------------------------

    public function test_ステータスを追加すると末尾のレーンになる(): void
    {
        $this->actingAs($this->admin)->post(route('projects.statuses.store', $this->project), [
            'name' => 'Blocked',
            'category' => StatusCategory::InProgress->value,
        ])->assertRedirect();

        $this->assertSame(
            ['未着手', '進行中', 'レビュー中', '完了', 'Blocked'],
            $this->project->statuses()->pluck('name')->all(),
        );
    }

    public function test_同じ名前のステータスは追加できない(): void
    {
        $this->actingAs($this->admin)->post(route('projects.statuses.store', $this->project), [
            'name' => '未着手',
            'category' => StatusCategory::Todo->value,
        ])->assertSessionHasErrors('name');
    }

    public function test_ステータスを改名してもカテゴリで完了判定が続く(): void
    {
        $done = $this->named('完了');
        $task = Issue::factory()->inProject($this->project, $this->admin)
            ->create(['status_id' => $done->id]);

        $this->actingAs($this->admin)->put(route('projects.statuses.update', [$this->project, $done]), [
            'name' => 'リリース済み',
            'category' => StatusCategory::Done->value,
        ])->assertRedirect();

        $this->assertSame('リリース済み', $done->refresh()->name);
        $this->assertTrue($task->refresh()->isCompleted());
    }

    public function test_最後の完了ステータスはカテゴリを変えられない(): void
    {
        $done = $this->named('完了');

        $this->actingAs($this->admin)->put(route('projects.statuses.update', [$this->project, $done]), [
            'name' => '完了',
            'category' => StatusCategory::InProgress->value,
        ])->assertSessionHasErrors('workflow');

        $this->assertTrue($done->refresh()->isDone());
        $this->assertNotNull($this->project->doneStatus());
    }

    public function test_ステータスを並べ替えられる(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('projects.statuses.move', [$this->project, $this->named('進行中')]), ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(
            ['進行中', '未着手', 'レビュー中', '完了'],
            $this->project->statuses()->pluck('name')->all(),
        );
    }

    public function test_先頭を上へ動かしても並びは変わらない(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('projects.statuses.move', [$this->project, $this->named('未着手')]), ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(
            ['未着手', '進行中', 'レビュー中', '完了'],
            $this->project->statuses()->pluck('name')->all(),
        );
    }

    public function test_並べ替えると初期ステータスも入れ替わる(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('projects.statuses.move', [$this->project, $this->named('進行中')]), ['direction' => 'up']);

        $this->assertSame('進行中', $this->project->initialStatus()->name);
    }

    public function test_課題の無いステータスはそのまま削除できる(): void
    {
        $status = $this->named('レビュー中');

        $this->actingAs($this->admin)
            ->delete(route('projects.statuses.destroy', [$this->project, $status]))
            ->assertRedirect(route('projects.edit', $this->project));

        $this->assertDatabaseMissing('statuses', ['id' => $status->id]);
    }

    public function test_ステータスを消すとその遷移も消える(): void
    {
        $status = $this->named('レビュー中');

        $this->assertTrue($this->project->transitions()
            ->where('from_status_id', $status->id)
            ->orWhere('to_status_id', $status->id)
            ->exists());

        $this->actingAs($this->admin)
            ->delete(route('projects.statuses.destroy', [$this->project, $status]));

        $this->assertFalse($this->project->transitions()
            ->where('from_status_id', $status->id)
            ->orWhere('to_status_id', $status->id)
            ->exists());
    }

    public function test_課題が残っているステータスは移送先なしでは消せない(): void
    {
        $status = $this->named('レビュー中');
        Issue::factory()->inProject($this->project, $this->admin)->create(['status_id' => $status->id]);

        $this->actingAs($this->admin)
            ->from(route('projects.statuses.delete', [$this->project, $status]))
            ->delete(route('projects.statuses.destroy', [$this->project, $status]))
            ->assertSessionHasErrors('workflow');

        $this->assertDatabaseHas('statuses', ['id' => $status->id]);
    }

    public function test_移送先を選べば課題を移してから消せる(): void
    {
        $status = $this->named('レビュー中');
        $destination = $this->named('進行中');
        $task = Issue::factory()->inProject($this->project, $this->admin)->create(['status_id' => $status->id]);

        $this->actingAs($this->admin)
            ->delete(route('projects.statuses.destroy', [$this->project, $status]), [
                'destination' => $destination->id,
            ])->assertRedirect(route('projects.edit', $this->project));

        $this->assertDatabaseMissing('statuses', ['id' => $status->id]);
        $this->assertSame($destination->id, $task->refresh()->status_id);
    }

    public function test_移送は履歴に残る(): void
    {
        $status = $this->named('レビュー中');
        $destination = $this->named('進行中');
        $task = Issue::factory()->inProject($this->project, $this->admin)->create(['status_id' => $status->id]);

        $this->actingAs($this->admin)
            ->delete(route('projects.statuses.destroy', [$this->project, $status]), [
                'destination' => $destination->id,
            ]);

        $this->assertDatabaseHas('activities', [
            'issue_id' => $task->id,
            'field' => 'status',
            'old_value' => 'レビュー中',
            'new_value' => '進行中',
        ]);
    }

    public function test_削除済みの課題が残っていても移送先が要る(): void
    {
        $status = $this->named('レビュー中');
        $task = Issue::factory()->inProject($this->project, $this->admin)->create(['status_id' => $status->id]);
        $task->delete();

        $this->actingAs($this->admin)
            ->from(route('projects.statuses.delete', [$this->project, $status]))
            ->delete(route('projects.statuses.destroy', [$this->project, $status]))
            ->assertSessionHasErrors('workflow');

        $this->assertDatabaseHas('statuses', ['id' => $status->id]);
    }

    public function test_最後の完了ステータスは削除できない(): void
    {
        $done = $this->named('完了');

        $this->actingAs($this->admin)
            ->from(route('projects.statuses.delete', [$this->project, $done]))
            ->delete(route('projects.statuses.destroy', [$this->project, $done]))
            ->assertSessionHasErrors('workflow');

        $this->assertDatabaseHas('statuses', ['id' => $done->id]);
    }

    public function test_最後のひとつになったステータスは削除できない(): void
    {
        $keep = $this->named('完了');

        foreach (['未着手', '進行中', 'レビュー中'] as $name) {
            $status = $this->named($name);
            $this->actingAs($this->admin)->delete(route('projects.statuses.destroy', [$this->project, $status]));
        }

        $this->assertSame(1, $this->project->statuses()->count());

        $this->actingAs($this->admin)
            ->from(route('projects.edit', $this->project))
            ->delete(route('projects.statuses.destroy', [$this->project, $keep]))
            ->assertSessionHasErrors('workflow');

        $this->assertNotNull($this->project->initialStatus());
        $this->assertNotNull($this->project->doneStatus());
    }

    public function test_他プロジェクトのステータスは操作できない(): void
    {
        $others = Project::factory()->withMember($this->admin)->create();
        $status = $others->initialStatus();

        $this->actingAs($this->admin)
            ->delete(route('projects.statuses.destroy', [$this->project, $status]))
            ->assertNotFound();
    }

    public function test_管理者以外はワークフローを触れない(): void
    {
        $member = User::factory()->create();
        $this->project->members()->create(['user_id' => $member->id, 'role' => ProjectRole::Member]);

        $this->actingAs($member)->post(route('projects.statuses.store', $this->project), [
            'name' => 'Blocked',
            'category' => StatusCategory::Todo->value,
        ])->assertForbidden();

        $this->actingAs($member)
            ->get(route('projects.statuses.delete', [$this->project, $this->named('未着手')]))
            ->assertForbidden();
    }

    // --- 遷移 ----------------------------------------------------------------

    public function test_遷移を足すとその順路が通れるようになる(): void
    {
        $task = Issue::factory()->inProject($this->project, $this->admin)
            ->create(['status_id' => $this->named('未着手')->id]);

        // 既定では 未着手 → レビュー中 は禁止されている
        $this->actingAs($this->admin)
            ->patch(route('tasks.transition', $task), ['status' => $this->named('レビュー中')->id])
            ->assertSessionHasErrors('status');

        $this->actingAs($this->admin)->post(route('projects.transitions.store', $this->project), [
            'from' => $this->named('未着手')->id,
            'to' => $this->named('レビュー中')->id,
        ])->assertRedirect();

        $this->actingAs($this->admin)
            ->patch(route('tasks.transition', $task), ['status' => $this->named('レビュー中')->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->named('レビュー中')->id, $task->refresh()->status_id);
    }

    public function test_どの状態からでもの遷移を足せる(): void
    {
        $this->actingAs($this->admin)->post(route('projects.transitions.store', $this->project), [
            'from' => '',
            'to' => $this->named('レビュー中')->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('transitions', [
            'project_id' => $this->project->id,
            'from_status_id' => null,
            'to_status_id' => $this->named('レビュー中')->id,
        ]);
    }

    public function test_同じ遷移は二重に登録できない(): void
    {
        $this->actingAs($this->admin)
            ->from(route('projects.edit', $this->project))
            ->post(route('projects.transitions.store', $this->project), [
                'from' => $this->named('未着手')->id,
                'to' => $this->named('進行中')->id,
            ])->assertSessionHasErrors('workflow');
    }

    public function test_同じステータスへの遷移は登録できない(): void
    {
        $this->actingAs($this->admin)
            ->from(route('projects.edit', $this->project))
            ->post(route('projects.transitions.store', $this->project), [
                'from' => $this->named('未着手')->id,
                'to' => $this->named('未着手')->id,
            ])->assertSessionHasErrors('workflow');
    }

    public function test_他プロジェクトのステータスを指す遷移は作れない(): void
    {
        $others = Project::factory()->withMember($this->admin)->create();

        $this->actingAs($this->admin)->post(route('projects.transitions.store', $this->project), [
            'from' => $this->named('未着手')->id,
            'to' => $others->initialStatus()->id,
        ])->assertSessionHasErrors('to');
    }

    public function test_遷移を消せる(): void
    {
        $transition = $this->project->transitions()->whereNotNull('from_status_id')->first();

        $this->actingAs($this->admin)
            ->delete(route('projects.transitions.destroy', [$this->project, $transition]))
            ->assertRedirect();

        $this->assertDatabaseMissing('transitions', ['id' => $transition->id]);
    }

    public function test_遷移を全部消すと全許可に戻る(): void
    {
        $this->project->transitions()->delete();

        $task = Issue::factory()->inProject($this->project, $this->admin)
            ->create(['status_id' => $this->named('未着手')->id]);

        $this->actingAs($this->admin)
            ->patch(route('tasks.transition', $task), ['status' => $this->named('レビュー中')->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->named('レビュー中')->id, $task->refresh()->status_id);
    }

    public function test_削除の確認画面に移送先と残っている課題が出る(): void
    {
        $status = $this->named('レビュー中');
        $task = Issue::factory()->inProject($this->project, $this->admin)
            ->create(['status_id' => $status->id, 'title' => 'レビュー待ちの課題']);

        $this->actingAs($this->admin)
            ->get(route('projects.statuses.delete', [$this->project, $status]))
            ->assertOk()
            ->assertSee('ステータスを削除')
            ->assertSee('レビュー待ちの課題')
            ->assertSee($task->key())
            // 自分以外のステータスだけが移送先に並ぶ
            ->assertSee('進行中 へ移す')
            ->assertDontSee('レビュー中 へ移す');
    }

    public function test_設定画面にワークフローが出る(): void
    {
        $this->actingAs($this->admin)
            ->get(route('projects.edit', $this->project))
            ->assertOk()
            ->assertSee('ワークフロー')
            ->assertSee('どの状態からでも');
    }
}
