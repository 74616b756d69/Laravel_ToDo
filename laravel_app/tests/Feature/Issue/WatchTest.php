<?php

namespace Tests\Feature\Issue;

use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 課題のウォッチと解除。
 */
class WatchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Project $project;

    private Issue $issue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->project = Project::personalFor($this->owner);
        $this->issue = Issue::factory()->inProject($this->project, $this->owner)->create();
    }

    private function memberWithRole(ProjectRole $role): User
    {
        $user = User::factory()->create();
        $this->project->members()->create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    public function test_ウォッチと解除ができる(): void
    {
        $member = $this->memberWithRole(ProjectRole::Member);

        $this->actingAs($member)
            ->from(route('tasks.show', $this->issue))
            ->post(route('tasks.watch', $this->issue))
            ->assertRedirect(route('tasks.show', $this->issue));
        $this->assertTrue($this->issue->isWatchedBy($member));

        $this->actingAs($member)->delete(route('tasks.unwatch', $this->issue));
        $this->assertFalse($this->issue->isWatchedBy($member));
    }

    public function test_二重にウォッチしても1行のまま(): void
    {
        $member = $this->memberWithRole(ProjectRole::Member);

        $this->actingAs($member)->post(route('tasks.watch', $this->issue));
        $this->actingAs($member)->post(route('tasks.watch', $this->issue));

        $this->assertSame(1, $this->issue->watchers()->whereKey($member->id)->count());
    }

    public function test_閲覧者もウォッチできる(): void
    {
        $viewer = $this->memberWithRole(ProjectRole::Viewer);

        $this->actingAs($viewer)->post(route('tasks.watch', $this->issue));

        $this->assertTrue($this->issue->isWatchedBy($viewer));
    }

    public function test_プロジェクト外の人はウォッチできない(): void
    {
        $outsider = User::factory()->create();

        // 見えない課題は 403 ではなく 404（課題の存在を漏らさない、既存の方針どおり）
        $this->actingAs($outsider)
            ->post(route('tasks.watch', $this->issue))
            ->assertNotFound();

        $this->assertFalse($this->issue->isWatchedBy($outsider));
    }

    public function test_詳細画面にウォッチの状態と人数が出る(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tasks.show', $this->issue))
            ->assertOk()
            ->assertSee('ウォッチ中');

        $member = $this->memberWithRole(ProjectRole::Member);

        $this->actingAs($member)
            ->get(route('tasks.show', $this->issue))
            ->assertOk()
            ->assertSee('ウォッチする');
    }

    public function test_課題を完全に消すとウォッチも消える(): void
    {
        $this->issue->forceDelete();

        $this->assertDatabaseMissing('issue_watchers', ['issue_id' => $this->issue->id]);
    }
}
