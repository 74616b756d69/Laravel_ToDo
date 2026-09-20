<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Project;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class BoardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    /** @var Collection<string, Status> */
    private Collection $statuses;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        // ボードは個人プロジェクトのワークフローを映す
        $this->project = Project::personalFor($this->user);
        $this->statuses = $this->project->statuses()->get()->keyBy('name');
    }

    private function named(string $name): Status
    {
        return $this->statuses[$name];
    }

    private function issueAt(string $statusName, array $attributes = []): Issue
    {
        return Issue::factory()
            ->inStatus($this->named($statusName))
            ->inProject($this->project, $this->user)
            ->create($attributes);
    }

    public function test_レーンはワークフローのステータスから作られる(): void
    {
        $lanes = $this->actingAs($this->user)->get(route('board'))->assertOk()->viewData('lanes');

        $this->assertSame(
            ['To Do', 'In Progress', 'In Review', 'Done'],
            $lanes->pluck('status.name')->all(),
        );
    }

    public function test_ステータスを増やすとレーンも増える(): void
    {
        $this->project->statuses()->create([
            'name' => 'Blocked', 'category' => 'in_progress', 'position' => 10,
        ]);

        $lanes = $this->actingAs($this->user)->get(route('board'))->assertOk()->viewData('lanes');

        $this->assertCount(5, $lanes);
        $this->assertSame('Blocked', $lanes->last()['status']->name);
    }

    public function test_ボードがステータスごとに表示される(): void
    {
        $this->issueAt('To Do', ['title' => '未着手の課題']);
        $this->issueAt('Done', ['title' => '完了の課題']);

        $lanes = $this->actingAs($this->user)->get(route('board'))->assertOk()
            ->viewData('lanes')->keyBy('status.name');

        $this->assertCount(1, $lanes['To Do']['tasks']);
        $this->assertCount(0, $lanes['In Progress']['tasks']);
        $this->assertCount(0, $lanes['In Review']['tasks']);
        $this->assertCount(1, $lanes['Done']['tasks']);
    }

    public function test_カードを別レーンへ移動するとステータスが変わる(): void
    {
        $task = $this->issueAt('To Do', ['completed_at' => null]);
        $done = $this->named('Done');

        $this->actingAs($this->user)
            ->patchJson(route('board.move', $task), [
                'status' => $done->id,
                'ids' => [$task->id],
            ])
            ->assertOk()
            ->assertJsonPath('status', $done->id)
            ->assertJsonPath('statusName', 'Done');

        $task->refresh();
        $this->assertSame($done->id, $task->status_id);
        $this->assertNotNull($task->completed_at);
    }

    public function test_完了から戻すと完了日時が消える(): void
    {
        $task = $this->issueAt('Done');

        $this->actingAs($this->user)->patchJson(route('board.move', $task), [
            'status' => $this->named('In Progress')->id,
            'ids' => [$task->id],
        ])->assertOk();

        $this->assertNull($task->refresh()->completed_at);
    }

    public function test_送った順序どおりに並び順が保存される(): void
    {
        $tasks = collect(range(1, 3))->map(fn () => $this->issueAt('To Do'));
        $reordered = $tasks->reverse()->values();

        $this->actingAs($this->user)->patchJson(route('board.move', $reordered->first()), [
            'status' => $this->named('To Do')->id,
            'ids' => $reordered->pluck('id')->all(),
        ])->assertOk();

        $this->assertSame(
            $reordered->pluck('id')->all(),
            Issue::orderBy('position')->pluck('id')->all(),
        );
    }

    public function test_参加していないプロジェクトの課題の並び順は書き換えられない(): void
    {
        $mine = $this->issueAt('To Do', ['position' => 0]);
        $others = Issue::factory()->create(['position' => 99]);

        // 他プロジェクトの ID を紛れ込ませても無視される
        $this->actingAs($this->user)->patchJson(route('board.move', $mine), [
            'status' => $this->named('To Do')->id,
            'ids' => [$others->id, $mine->id],
        ])->assertOk();

        $this->assertSame(99, $others->refresh()->position);
        $this->assertSame(0, $mine->refresh()->position);
    }

    public function test_参加していないプロジェクトの課題は移動できない(): void
    {
        $others = Issue::factory()->create();

        $this->actingAs($this->user)
            ->patchJson(route('board.move', $others), [
                'status' => $this->named('Done')->id,
                'ids' => [$others->id],
            ])
            ->assertForbidden();
    }

    public function test_存在しないステータスへは移動できない(): void
    {
        $task = $this->issueAt('To Do');

        $this->actingAs($this->user)
            ->patchJson(route('board.move', $task), ['status' => 999999, 'ids' => [$task->id]])
            ->assertNotFound();
    }

    public function test_別プロジェクトのステータスへは移動できない(): void
    {
        $task = $this->issueAt('To Do');
        $foreign = Project::factory()->create()->statuses()->first();

        $this->actingAs($this->user)
            ->patchJson(route('board.move', $task), ['status' => $foreign->id, 'ids' => [$task->id]])
            ->assertNotFound();
    }

    // --- ワークフローの制約 ----------------------------------------------------

    public function test_許可されていない遷移は422と理由を返す(): void
    {
        $task = $this->issueAt('To Do');

        $this->actingAs($this->user)
            ->patchJson(route('board.move', $task), [
                'status' => $this->named('In Review')->id,
                'ids' => [$task->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('from', 'To Do')
            ->assertJsonPath('to', 'In Review')
            ->assertJsonFragment(['message' => '「To Do」から「In Review」へは変更できません。このプロジェクトのワークフローで許可されていない遷移です。']);
    }

    public function test_許可されていない遷移では状態も並び順も変わらない(): void
    {
        $task = $this->issueAt('To Do', ['position' => 7]);

        $this->actingAs($this->user)->patchJson(route('board.move', $task), [
            'status' => $this->named('In Review')->id,
            'ids' => [$task->id],
        ])->assertStatus(422);

        $task->refresh();
        $this->assertSame($this->named('To Do')->id, $task->status_id);
        $this->assertSame(7, $task->position);
    }

    public function test_画面に遷移表が渡される(): void
    {
        $map = $this->actingAs($this->user)->get(route('board'))->assertOk()
            ->viewData('allowedTransitions');

        $todo = $this->named('To Do')->id;
        $review = $this->named('In Review')->id;

        // JS はこの表を見てドロップ自体を止める
        $this->assertNotContains($review, $map[$todo]);
        $this->assertContains($this->named('In Progress')->id, $map[$todo]);
        // 自分自身（レーン内の並べ替え）は常に許可
        $this->assertContains($todo, $map[$todo]);
    }
}
