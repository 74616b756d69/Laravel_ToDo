<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesWorkflow;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase, UsesWorkflow;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_ダッシュボードが表示される(): void
    {
        $this->actingAs($this->user)->get(route('dashboard'))->assertOk()->assertSee('完了率');
    }

    public function test_完了率が計算される(): void
    {
        Issue::factory()->count(3)->forUser($this->user)->create(['status_id' => $this->statusIdFor($this->user, 'To Do'), 'due_date' => null]);
        Issue::factory()->forUser($this->user)->completed()->create();

        $totals = $this->actingAs($this->user)->get(route('dashboard'))->viewData('totals');

        $this->assertSame(4, $totals['total']);
        $this->assertSame(1, $totals['done']);
        $this->assertSame(3, $totals['open']);
        $this->assertSame(25, $totals['rate']);
    }

    public function test_タスクが無くても完了率は_0で割られない(): void
    {
        $this->assertSame(
            0,
            $this->actingAs($this->user)->get(route('dashboard'))->viewData('totals')['rate'],
        );
    }

    public function test_推移は日付が抜けていても連続した系列になる(): void
    {
        Issue::factory()->forUser($this->user)->create([
            'status_id' => $this->statusIdFor($this->user, 'Done'),
            'completed_at' => now()->subDays(2),
        ]);

        $trend = $this->actingAs($this->user)->get(route('dashboard'))->viewData('trend');

        $this->assertCount(14, $trend);
        $this->assertSame(1, $trend->sum('count'));
        $this->assertSame(1, $trend[11]['count']); // 2日前
        $this->assertSame(0, $trend[13]['count']); // 今日
    }

    public function test_優先度別の内訳は未完了のみを数える(): void
    {
        Issue::factory()->count(2)->forUser($this->user)->create([
            'status_id' => $this->statusIdFor($this->user, 'To Do'),
            'priority' => TaskPriority::High,
        ]);
        Issue::factory()->forUser($this->user)->completed()->create(['priority' => TaskPriority::High]);

        $byPriority = $this->actingAs($this->user)->get(route('dashboard'))->viewData('byPriority');

        $this->assertSame(2, $byPriority->firstWhere('priority', TaskPriority::High)['count']);
        $this->assertSame(0, $byPriority->firstWhere('priority', TaskPriority::Low)['count']);
    }

    public function test_連続達成日数が数えられる(): void
    {
        foreach ([0, 1, 2, 4] as $daysAgo) {
            Issue::factory()->forUser($this->user)->create([
                'status_id' => $this->statusIdFor($this->user, 'Done'),
                'completed_at' => now()->subDays($daysAgo),
            ]);
        }

        // 3日前が空いているので、連続は今日から3日分
        $this->assertSame(3, $this->actingAs($this->user)->get(route('dashboard'))->viewData('streak'));
    }

    public function test_他人のデータは集計に混ざらない(): void
    {
        Issue::factory()->count(5)->create();
        Issue::factory()->forUser($this->user)->create(['due_date' => null]);

        $this->assertSame(
            1,
            $this->actingAs($this->user)->get(route('dashboard'))->viewData('totals')['total'],
        );
    }

    public function test_よく使うタグが件数順に並ぶ(): void
    {
        $少 = Tag::factory()->for($this->user)->create(['name' => 'あまり使わない']);
        $多 = Tag::factory()->for($this->user)->create(['name' => 'よく使う']);

        Issue::factory()->forUser($this->user)->create()->tags()->attach($少);
        Issue::factory()->count(3)->forUser($this->user)->create()
            ->each(fn (Issue $task) => $task->tags()->attach($多));

        $topTags = $this->actingAs($this->user)->get(route('dashboard'))->viewData('topTags');

        $this->assertSame('よく使う', $topTags->first()->name);
    }
}
