<?php

namespace Tests\Feature\Sprint;

use App\Enums\StatusCategory;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Services\SprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VelocityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private SprintService $sprints;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::personalFor($this->user);
        $this->sprints = app(SprintService::class);
    }

    private function issueIn(Sprint $sprint, int $points, StatusCategory $category = StatusCategory::Todo): Issue
    {
        $issue = Issue::factory()->inProject($this->project, $this->user)->inCategory($category)->create(['story_points' => $points]);
        $issue->forceFill(['sprint_id' => $sprint->id])->save();

        return $issue;
    }

    public function test_開始時に約束した量を完了時にやり終えた量を写し取る(): void
    {
        $sprint = Sprint::factory()->for($this->project)->create(['name' => 'S1']);
        $this->issueIn($sprint, 5);
        $done = $this->issueIn($sprint, 3);
        $this->issueIn($sprint, 8);

        $this->sprints->start($sprint);
        $this->assertSame(16, $sprint->fresh()->committed_points);

        // 途中で 1 件終わらせ、1 件足す（約束の量は変わらない）
        $done->forceFill(['status_id' => $this->project->doneStatus()->id, 'completed_at' => now()])->save();
        $this->issueIn($sprint, 2);

        $this->sprints->complete($sprint);

        $sprint->refresh();
        $this->assertSame(16, $sprint->committed_points);
        $this->assertSame(3, $sprint->completed_points);
    }

    public function test_閉じたスプリントを古い順に返し写しの無いものは完了分を数え直す(): void
    {
        $old = Sprint::factory()->for($this->project)->closed()->create(['name' => '古い', 'end_date' => '2026-09-01']);
        $this->issueIn($old, 5, StatusCategory::Done);
        $this->issueIn($old, 2, StatusCategory::Done);

        $new = Sprint::factory()->for($this->project)->closed()->create([
            'name' => '新しい', 'end_date' => '2026-09-15', 'committed_points' => 20, 'completed_points' => 13,
        ]);

        $velocity = $this->sprints->velocityFor($this->project);

        $this->assertSame(['古い', '新しい'], array_map(fn ($row) => $row['sprint']->name, $velocity));
        $this->assertNull($velocity[0]['committed']);
        $this->assertSame(7, $velocity[0]['completed']);
        $this->assertSame(20, $velocity[1]['committed']);
        $this->assertSame(13, $velocity[1]['completed']);
    }

    public function test_進行中と未開始のスプリントは数えない(): void
    {
        Sprint::factory()->for($this->project)->active()->create();
        Sprint::factory()->for($this->project)->create();

        $this->assertSame([], $this->sprints->velocityFor($this->project));
    }

    public function test_分析画面にベロシティが出る(): void
    {
        Sprint::factory()->for($this->project)->closed()->create(['name' => 'スプリント 3', 'committed_points' => 10, 'completed_points' => 8]);

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ベロシティ')
            ->assertSee('スプリント 3：約束 10 / 完了 8 ポイント');
    }
}
