<?php

namespace Tests\Feature\Task;

use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesWorkflow;
use Tests\TestCase;

/**
 * 参加していないプロジェクトの課題に一切アクセスできないことを担保する。
 *
 * 移行前は「他人のタスク（user_id 不一致）」を弾いていた。判定の根拠が
 * project_members.role に変わったので、前提を「非メンバー」に置き換えている。
 * 役割ごとの細かい出し分けは IssuePolicyTest を参照。
 */
class TaskAuthorizationTest extends TestCase
{
    use RefreshDatabase, UsesWorkflow;

    private User $user;

    private Issue $othersTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        // 素のファクトリは新しいプロジェクトと起票者を作る = 自分は非メンバー
        $this->othersTask = Issue::factory()->create();
    }

    public function test_参加していないプロジェクトの課題は閲覧できない(): void
    {
        $this->actingAs($this->user)
            ->get(route('tasks.show', $this->othersTask))
            ->assertForbidden();
    }

    public function test_参加していないプロジェクトの課題は更新できない(): void
    {
        // 書き換えは詳細画面のインライン更新だけなので、その入口で止まることを見る
        $this->actingAs($this->user)
            ->patch(route('tasks.title', $this->othersTask), ['title' => '乗っ取り'])
            ->assertForbidden();

        $this->assertNotSame('乗っ取り', $this->othersTask->refresh()->title);
    }

    public function test_参加していないプロジェクトの課題は削除できない(): void
    {
        $this->actingAs($this->user)
            ->delete(route('tasks.destroy', $this->othersTask))
            ->assertForbidden();

        $this->assertNotSoftDeleted($this->othersTask);
    }

    public function test_参加していないプロジェクトの課題は完了トグルできない(): void
    {
        $this->actingAs($this->user)
            ->patch(route('tasks.completion', $this->othersTask))
            ->assertForbidden();
    }

    /**
     * 認可と可視範囲が同じ根拠を見ていること。
     * Policy は許すのに一覧に出ない（またはその逆）という捻れを防ぐ。
     */
    public function test_同じプロジェクトの同僚の課題は見えるし編集もできる(): void
    {
        $colleague = User::factory()->create();
        $project = Project::factory()
            ->withMember($this->user, ProjectRole::Member)
            ->withMember($colleague, ProjectRole::Member)
            ->create();

        $task = Issue::factory()->inProject($project, $colleague)->create(['title' => '同僚の課題']);

        $this->actingAs($this->user)
            ->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('同僚の課題');

        $this->actingAs($this->user)
            ->patch(route('tasks.title', $task), ['title' => '同僚の課題（更新）'])
            ->assertSessionHasNoErrors();

        $this->assertSame('同僚の課題（更新）', $task->refresh()->title);
    }
}
