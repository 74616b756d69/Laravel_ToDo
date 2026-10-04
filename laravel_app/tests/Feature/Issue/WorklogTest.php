<?php

namespace Tests\Feature\Issue;

use App\Enums\ActivityField;
use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Models\Worklog;
use App\Support\Duration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 作業時間（見積もりと実績）。
 */
class WorklogTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private User $viewer;

    private Project $project;

    private Issue $issue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 10:00:00');

        $this->owner = User::factory()->create(['name' => '管理者']);
        $this->member = User::factory()->create(['name' => 'メンバー']);
        $this->viewer = User::factory()->create();
        $this->project = Project::personalFor($this->owner);
        $this->project->members()->create(['user_id' => $this->member->id, 'role' => ProjectRole::Member]);
        $this->project->members()->create(['user_id' => $this->viewer->id, 'role' => ProjectRole::Viewer]);

        $this->issue = Issue::factory()->inProject($this->project, $this->owner)->create();
    }

    /** @return array<string, array{string, ?int}> */
    public static function durations(): array
    {
        return [
            '時間と分' => ['1h30m', 90],
            '空白入り' => ['1h 30m', 90],
            '分だけ' => ['45m', 45],
            '小数の時間' => ['1.5h', 90],
            '単位なしは時間' => ['2', 120],
            '日は 8 時間' => ['1d', 480],
            '日本語' => ['1時間30分', 90],
            '0 は記録しない' => ['0m', null],
            '読めない' => ['そこそこ', null],
            '単位の抜け' => ['2h5', null],
        ];
    }

    #[DataProvider('durations')]
    public function test_作業時間の書き方を読む(string $input, ?int $expected): void
    {
        $this->assertSame($expected, Duration::parse($input));
    }

    public function test_表示は時間と分(): void
    {
        $this->assertSame('1時間30分', Duration::format(90));
        $this->assertSame('45分', Duration::format(45));
        $this->assertSame('2時間', Duration::format(120));
        $this->assertSame('1h30m', Duration::toInput(90));
    }

    public function test_見積もり時間を設定でき履歴に残る(): void
    {
        $this->actingAs($this->member)
            ->patch(route('tasks.estimate', $this->issue), ['estimate' => '1d'])
            ->assertSessionHasNoErrors();

        $this->assertSame(480, $this->issue->fresh()->original_estimate_minutes);
        $activity = $this->issue->activities()->where('field', ActivityField::Estimate)->sole();
        $this->assertSame('8時間', $activity->new_value);

        $this->actingAs($this->member)->patch(route('tasks.estimate', $this->issue), ['estimate' => '']);
        $this->assertNull($this->issue->fresh()->original_estimate_minutes);
    }

    public function test_読めない見積もりは弾く(): void
    {
        $this->actingAs($this->member)
            ->patch(route('tasks.estimate', $this->issue), ['estimate' => 'たくさん'])
            ->assertSessionHasErrors('estimate');
    }

    public function test_作業時間を記録すると記録者はログイン中の人になる(): void
    {
        $this->actingAs($this->member)->post(route('worklogs.store', $this->issue), [
            'time' => '1h30m',
            'worked_on' => '2026-10-06',
            'comment' => 'ログイン周りの調査',
            'user_id' => $this->owner->id,
        ])->assertRedirect(route('tasks.show', $this->issue));

        $worklog = Worklog::sole();
        $this->assertSame(90, $worklog->minutes);
        $this->assertSame($this->member->id, $worklog->user_id);
        $this->assertSame('2026-10-06', $worklog->worked_on->toDateString());

        $activity = $this->issue->activities()->where('field', ActivityField::Worklog)->sole();
        $this->assertSame('作業時間 1時間30分（10/6） を記録しました。', $activity->field->describe($activity->old_value, $activity->new_value));
    }

    public function test_先の日付と大きすぎる時間は記録できない(): void
    {
        $this->actingAs($this->member)
            ->post(route('worklogs.store', $this->issue), ['time' => '1h', 'worked_on' => '2026-10-08'])
            ->assertSessionHasErrorsIn('worklog', 'worked_on');

        $this->actingAs($this->member)
            ->post(route('worklogs.store', $this->issue), ['time' => '25h', 'worked_on' => '2026-10-07'])
            ->assertSessionHasErrorsIn('worklog', 'time');

        $this->assertDatabaseCount('worklogs', 0);
    }

    public function test_閲覧者は記録できない(): void
    {
        $this->actingAs($this->viewer)
            ->post(route('worklogs.store', $this->issue), ['time' => '1h', 'worked_on' => '2026-10-07'])
            ->assertForbidden();
    }

    public function test_自分の記録は消せるが他人の記録は消せず管理者は消せる(): void
    {
        $mine = Worklog::factory()->for($this->issue)->create(['user_id' => $this->member->id]);
        $others = Worklog::factory()->for($this->issue)->create(['user_id' => $this->owner->id]);

        $this->actingAs($this->member)->delete(route('worklogs.destroy', [$this->issue, $others]))->assertForbidden();
        $this->actingAs($this->member)->delete(route('worklogs.destroy', [$this->issue, $mine]))->assertRedirect();
        $this->assertModelMissing($mine);

        $theirs = Worklog::factory()->for($this->issue)->create(['user_id' => $this->member->id]);
        $this->actingAs($this->owner)->delete(route('worklogs.destroy', [$this->issue, $theirs]))->assertRedirect();
        $this->assertModelMissing($theirs);
    }

    public function test_別の課題の_URL_に差し替えて消すことはできない(): void
    {
        $worklog = Worklog::factory()->for($this->issue)->create(['user_id' => $this->owner->id]);
        $other = Issue::factory()->inProject($this->project, $this->owner)->create();

        $this->actingAs($this->owner)->delete(route('worklogs.destroy', [$other, $worklog]))->assertNotFound();
    }

    public function test_課題画面に見積もりと実績と超過が出る(): void
    {
        $this->issue->update(['original_estimate_minutes' => 60]);
        Worklog::factory()->for($this->issue)->create(['user_id' => $this->member->id, 'minutes' => 90]);

        $this->actingAs($this->owner)->get(route('tasks.show', $this->issue))
            ->assertOk()
            ->assertSee('作業時間')
            ->assertSee('1時間30分')
            ->assertSee('30分 超過');
    }

    public function test_分析画面に今週の人ごとの作業時間が出る(): void
    {
        Worklog::factory()->for($this->issue)->create(['user_id' => $this->member->id, 'minutes' => 120, 'worked_on' => today()]);
        Worklog::factory()->for($this->issue)->create(['user_id' => $this->member->id, 'minutes' => 30, 'worked_on' => today()]);
        // 先月の分は数えない
        Worklog::factory()->for($this->issue)->create(['user_id' => $this->owner->id, 'minutes' => 600, 'worked_on' => '2026-09-01']);

        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('今週の作業時間')
            ->assertSee('2時間30分')
            ->assertDontSee('10時間');
    }

    public function test_見えないプロジェクトの作業時間は分析に出ない(): void
    {
        $stranger = User::factory()->create(['name' => '他社の人']);
        $foreign = Issue::factory()->forUser($stranger)->create();
        Worklog::factory()->for($foreign)->create(['user_id' => $stranger->id, 'minutes' => 300]);

        $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertDontSee('他社の人');
    }
}
