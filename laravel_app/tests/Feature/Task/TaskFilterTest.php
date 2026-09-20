<?php

namespace Tests\Feature\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_キーワードでタイトルと内容を検索できる(): void
    {
        Task::factory()->for($this->user)->create(['title' => '請求書の作成', 'content' => null]);
        Task::factory()->for($this->user)->create(['title' => '買い物', 'content' => '請求書を投函する']);
        Task::factory()->for($this->user)->create(['title' => '散歩', 'content' => null]);

        $this->actingAs($this->user)
            ->get(route('tasks.index', ['keyword' => '請求書']))
            ->assertOk()
            ->assertSee('請求書の作成')
            ->assertSee('買い物')
            ->assertDontSee('散歩');
    }

    public function test_ステータスで絞り込める(): void
    {
        Task::factory()->for($this->user)->create(['title' => '進行中タスク', 'status' => TaskStatus::Doing]);
        Task::factory()->for($this->user)->create(['title' => '未着手タスク', 'status' => TaskStatus::Todo]);

        $this->actingAs($this->user)
            ->get(route('tasks.index', ['status' => 'doing']))
            ->assertSee('進行中タスク')
            ->assertDontSee('未着手タスク');
    }

    public function test_優先度で絞り込める(): void
    {
        Task::factory()->for($this->user)->create(['title' => '重要タスク', 'priority' => TaskPriority::High]);
        Task::factory()->for($this->user)->create(['title' => '普通タスク', 'priority' => TaskPriority::Low]);

        $this->actingAs($this->user)
            ->get(route('tasks.index', ['priority' => 'high']))
            ->assertSee('重要タスク')
            ->assertDontSee('普通タスク');
    }

    public function test_期限切れのみに絞り込める(): void
    {
        Task::factory()->for($this->user)->overdue()->create(['title' => '遅れているタスク']);
        Task::factory()->for($this->user)->create([
            'title' => '余裕のあるタスク',
            'status' => TaskStatus::Todo,
            'due_date' => today()->addWeek(),
        ]);

        $this->actingAs($this->user)
            ->get(route('tasks.index', ['overdue' => 1]))
            ->assertSee('遅れているタスク')
            ->assertDontSee('余裕のあるタスク');
    }

    public function test_完了済みは期限切れに数えない(): void
    {
        Task::factory()->for($this->user)->create([
            'title' => '完了した昔のタスク',
            'status' => TaskStatus::Done,
            'completed_at' => now(),
            'due_date' => today()->subMonth(),
        ]);

        $this->actingAs($this->user)
            ->get(route('tasks.index', ['overdue' => 1]))
            ->assertDontSee('完了した昔のタスク');
    }

    public function test_優先度順に並び替えられる(): void
    {
        Task::factory()->for($this->user)->create(['title' => '低優先', 'priority' => TaskPriority::Low]);
        Task::factory()->for($this->user)->create(['title' => '高優先', 'priority' => TaskPriority::High]);
        Task::factory()->for($this->user)->create(['title' => '中優先', 'priority' => TaskPriority::Medium]);

        $titles = $this->actingAs($this->user)
            ->get(route('tasks.index', ['sort' => 'priority']))
            ->viewData('tasks')
            ->pluck('title')
            ->all();

        $this->assertSame(['高優先', '中優先', '低優先'], $titles);
    }

    public function test_期限の近い順に並び替えられ期限なしは末尾になる(): void
    {
        Task::factory()->for($this->user)->create(['title' => '期限なし', 'due_date' => null]);
        Task::factory()->for($this->user)->create(['title' => '来週', 'due_date' => today()->addWeek()]);
        Task::factory()->for($this->user)->create(['title' => '明日', 'due_date' => today()->addDay()]);

        $titles = $this->actingAs($this->user)
            ->get(route('tasks.index', ['sort' => 'due_date']))
            ->viewData('tasks')
            ->pluck('title')
            ->all();

        $this->assertSame(['明日', '来週', '期限なし'], $titles);
    }

    public function test_11件目以降はページ送りされる(): void
    {
        Task::factory()->count(11)->for($this->user)->create();

        $tasks = $this->actingAs($this->user)
            ->get(route('tasks.index'))
            ->viewData('tasks');

        $this->assertCount(10, $tasks);
        $this->assertSame(11, $tasks->total());
    }

    public function test_集計にはステータス別の件数が入る(): void
    {
        // 期限は明示的に外しておく（ファクトリの既定はランダムで期限切れになり得るため）
        Task::factory()->count(2)->for($this->user)->create([
            'status' => TaskStatus::Todo,
            'due_date' => null,
        ]);
        Task::factory()->for($this->user)->completed()->create();
        Task::factory()->for($this->user)->overdue()->create();

        $summary = $this->actingAs($this->user)
            ->get(route('tasks.index'))
            ->viewData('summary');

        $this->assertSame(4, $summary['total']);
        $this->assertSame(3, $summary['todo']);
        $this->assertSame(1, $summary['done']);
        $this->assertSame(1, $summary['overdue']);
    }
}
