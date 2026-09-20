<?php

namespace Tests\Feature\Sprint;

use App\Enums\StatusCategory;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Services\SprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 進行中スプリントのバーンダウン。
 *
 * 日ごとのスナップショットを持たないので、完了時刻から残量を逆算している。
 * 「今日より先は線を引かない」「見積り前でも件数で成立する」あたりを固定する。
 */
class BurndownTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private SprintService $sprints;

    protected function setUp(): void
    {
        parent::setUp();

        // 10 日スプリントの 5 日目にいる状態にする
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));

        $this->user = User::factory()->create();
        $this->project = Project::personalFor($this->user);
        $this->sprints = app(SprintService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function activeSprint(): Sprint
    {
        return Sprint::factory()->for($this->project)->active('2026-10-01', '2026-10-11')->create();
    }

    private function issue(Sprint $sprint, ?int $points, ?string $completedAt = null): Issue
    {
        $factory = Issue::factory()->inProject($this->project, $this->user);

        $factory = $completedAt === null
            ? $factory->inCategory(StatusCategory::Todo)
            : $factory->completed();

        return $factory->create([
            'sprint_id' => $sprint->id,
            'story_points' => $points,
            'completed_at' => $completedAt,
        ]);
    }

    public function test_進行中スプリントが無ければnull(): void
    {
        Sprint::factory()->for($this->project)->create();

        $this->assertNull($this->sprints->burndownFor($this->project));
    }

    public function test_課題が無ければnull(): void
    {
        $this->activeSprint();

        $this->assertNull($this->sprints->burndownFor($this->project));
    }

    public function test_期間が未設定ならnull(): void
    {
        $sprint = Sprint::factory()->for($this->project)->create(['name' => '期間なし']);
        $this->sprints->start($sprint);
        // start() が開始日を今日で埋めるので、終了日だけ空にする
        $sprint->forceFill(['end_date' => null])->save();

        $this->issue($sprint->refresh(), 3);

        $this->assertNull($this->sprints->burndownFor($this->project));
    }

    public function test_ストーリーポイントで数える(): void
    {
        $sprint = $this->activeSprint();

        $this->issue($sprint, 5, '2026-10-02 10:00:00');
        $this->issue($sprint, 3, '2026-10-04 10:00:00');
        $this->issue($sprint, 2);

        $burndown = $this->sprints->burndownFor($this->project);

        $this->assertSame(10, $burndown['total']);
        $this->assertSame('ポイント', $burndown['unit']);

        $remaining = $burndown['days']->pluck('remaining', 'date.day');

        $this->assertSame(10, $remaining[1]);  // 10/1 開始時
        $this->assertSame(5, $remaining[2]);   // 5pt 完了
        $this->assertSame(5, $remaining[3]);   // 変化なし
        $this->assertSame(2, $remaining[4]);   // さらに 3pt 完了
        $this->assertSame(2, $remaining[5]);   // 今日
    }

    public function test_見積りが無ければ件数で数える(): void
    {
        $sprint = $this->activeSprint();

        $this->issue($sprint, null, '2026-10-03 10:00:00');
        $this->issue($sprint, null);
        $this->issue($sprint, null);

        $burndown = $this->sprints->burndownFor($this->project);

        $this->assertSame(3, $burndown['total']);
        $this->assertSame('件', $burndown['unit']);
        $this->assertSame(2, $burndown['days']->pluck('remaining', 'date.day')[3]);
    }

    public function test_未来の日付には線を引かない(): void
    {
        $sprint = $this->activeSprint();
        $this->issue($sprint, 5);

        $burndown = $this->sprints->burndownFor($this->project);

        $byDay = $burndown['days']->keyBy(fn (array $day) => $day['date']->day);

        // 今日（10/5）までは実績がある
        $this->assertNotNull($byDay[5]['remaining']);
        // 明日以降は null
        $this->assertNull($byDay[6]['remaining']);
        $this->assertNull($byDay[11]['remaining']);
    }

    public function test_理想線は総量からゼロへ引かれる(): void
    {
        $sprint = $this->activeSprint();
        $this->issue($sprint, 10);

        $days = $this->sprints->burndownFor($this->project)['days'];

        $this->assertSame(10.0, $days->first()['ideal']);
        $this->assertSame(0.0, $days->last()['ideal']);
        // 10 日スプリントなので 1 日あたり 1 ポイント落ちる
        $this->assertSame(9.0, $days[1]['ideal']);
    }

    public function test_日数は開始日から終了日までそろう(): void
    {
        $sprint = $this->activeSprint();
        $this->issue($sprint, 1);

        $days = $this->sprints->burndownFor($this->project)['days'];

        // 10/1 〜 10/11 の 11 日分
        $this->assertCount(11, $days);
        $this->assertSame('2026-10-01', $days->first()['date']->toDateString());
        $this->assertSame('2026-10-11', $days->last()['date']->toDateString());
    }

    public function test_ダッシュボードにバーンダウンが出る(): void
    {
        $sprint = $this->activeSprint();
        $this->issue($sprint, 5, '2026-10-02 10:00:00');
        $this->issue($sprint, 3);

        $response = $this->actingAs($this->user)->get(route('dashboard'))->assertOk();

        $burndown = $response->viewData('burndown');

        $this->assertNotNull($burndown);
        $this->assertSame(8, $burndown['total']);
        $response->assertSee('バーンダウン')->assertSee($sprint->name);
    }

    public function test_進行中スプリントが無ければダッシュボードに出ない(): void
    {
        Issue::factory()->inProject($this->project, $this->user)->create();

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('バーンダウン');
    }

    public function test_他プロジェクトのスプリントは混ざらない(): void
    {
        $mine = $this->activeSprint();
        $this->issue($mine, 5);

        $other = Project::factory()->withMember($this->user)->create();
        $otherSprint = Sprint::factory()->for($other)->active('2026-10-01', '2026-10-11')->create();
        Issue::factory()->inProject($other, $this->user)->create([
            'sprint_id' => $otherSprint->id,
            'story_points' => 99,
        ]);

        // 個人プロジェクトのスプリントだけを見る
        $this->assertSame(5, $this->sprints->burndownFor($this->project)['total']);
    }
}
