<?php

namespace Tests\Feature\Task;

use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesWorkflow;
use Tests\TestCase;

class TaskFilterTest extends TestCase
{
    use RefreshDatabase, UsesWorkflow;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_キーワードでタイトルと内容を検索できる(): void
    {
        Issue::factory()->forUser($this->user)->create(['title' => '請求書の作成', 'content' => null]);
        Issue::factory()->forUser($this->user)->create(['title' => '買い物', 'content' => '請求書を投函する']);
        Issue::factory()->forUser($this->user)->create(['title' => '散歩', 'content' => null]);

        $this->actingAs($this->user)
            ->get(route('tasks.index', ['keyword' => '請求書']))
            ->assertOk()
            ->assertSee('請求書の作成')
            ->assertSee('買い物')
            ->assertDontSee('散歩');
    }

    public function test_ステータスで絞り込める(): void
    {
        Issue::factory()->forUser($this->user)->create(['title' => '進行中タスク', 'status_id' => $this->statusIdFor($this->user, 'In Progress')]);
        Issue::factory()->forUser($this->user)->create(['title' => '未着手タスク', 'status_id' => $this->statusIdFor($this->user, 'To Do')]);

        $this->actingAs($this->user)
            ->get(route('tasks.index', ['status' => $this->statusIdFor($this->user, 'In Progress')]))
            ->assertSee('進行中タスク')
            ->assertDontSee('未着手タスク');
    }

    public function test_優先度で絞り込める(): void
    {
        Issue::factory()->forUser($this->user)->create(['title' => '重要タスク', 'priority' => TaskPriority::High]);
        Issue::factory()->forUser($this->user)->create(['title' => '普通タスク', 'priority' => TaskPriority::Low]);

        $this->actingAs($this->user)
            ->get(route('tasks.index', ['priority' => 'high']))
            ->assertSee('重要タスク')
            ->assertDontSee('普通タスク');
    }

    public function test_期限切れのみに絞り込める(): void
    {
        Issue::factory()->forUser($this->user)->overdue()->create(['title' => '遅れているタスク']);
        Issue::factory()->forUser($this->user)->create([
            'title' => '余裕のあるタスク',
            'status_id' => $this->statusIdFor($this->user, 'To Do'),
            'due_date' => today()->addWeek(),
        ]);

        $this->actingAs($this->user)
            ->get(route('tasks.index', ['overdue' => 1]))
            ->assertSee('遅れているタスク')
            ->assertDontSee('余裕のあるタスク');
    }

    public function test_完了済みは期限切れに数えない(): void
    {
        Issue::factory()->forUser($this->user)->create([
            'title' => '完了した昔のタスク',
            'status_id' => $this->statusIdFor($this->user, 'Done'),
            'completed_at' => now(),
            'due_date' => today()->subMonth(),
        ]);

        $this->actingAs($this->user)
            ->get(route('tasks.index', ['overdue' => 1]))
            ->assertDontSee('完了した昔のタスク');
    }

    public function test_優先度順に並び替えられる(): void
    {
        Issue::factory()->forUser($this->user)->create(['title' => '低優先', 'priority' => TaskPriority::Low]);
        Issue::factory()->forUser($this->user)->create(['title' => '高優先', 'priority' => TaskPriority::High]);
        Issue::factory()->forUser($this->user)->create(['title' => '中優先', 'priority' => TaskPriority::Medium]);

        $titles = $this->actingAs($this->user)
            ->get(route('tasks.index', ['sort' => 'priority']))
            ->viewData('tasks')
            ->pluck('title')
            ->all();

        $this->assertSame(['高優先', '中優先', '低優先'], $titles);
    }

    public function test_期限の近い順に並び替えられ期限なしは末尾になる(): void
    {
        Issue::factory()->forUser($this->user)->create(['title' => '期限なし', 'due_date' => null]);
        Issue::factory()->forUser($this->user)->create(['title' => '来週', 'due_date' => today()->addWeek()]);
        Issue::factory()->forUser($this->user)->create(['title' => '明日', 'due_date' => today()->addDay()]);

        $titles = $this->actingAs($this->user)
            ->get(route('tasks.index', ['sort' => 'due_date']))
            ->viewData('tasks')
            ->pluck('title')
            ->all();

        $this->assertSame(['明日', '来週', '期限なし'], $titles);
    }

    public function test_11件目以降はページ送りされる(): void
    {
        Issue::factory()->count(11)->forUser($this->user)->create();

        $tasks = $this->actingAs($this->user)
            ->get(route('tasks.index'))
            ->viewData('tasks');

        $this->assertCount(10, $tasks);
        $this->assertSame(11, $tasks->total());
    }

    public function test_集計にはステータス別の件数が入る(): void
    {
        // 期限は明示的に外しておく（ファクトリの既定はランダムで期限切れになり得るため）
        Issue::factory()->count(2)->forUser($this->user)->create([
            'status_id' => $this->statusIdFor($this->user, 'To Do'),
            'due_date' => null,
        ]);
        Issue::factory()->forUser($this->user)->completed()->create();
        Issue::factory()->forUser($this->user)->overdue()->create();

        $summary = $this->actingAs($this->user)
            ->get(route('tasks.index'))
            ->viewData('summary');

        $this->assertSame(4, $summary['total']);
        $this->assertSame(3, $summary['todo']);
        $this->assertSame(1, $summary['done']);
        $this->assertSame(1, $summary['overdue']);
    }
}
