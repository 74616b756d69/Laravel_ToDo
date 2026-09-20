<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 役割ごとに何ができるかを固定する。
 * 「所属していない人が一切触れない」ことが最優先の担保。
 */
class ProjectAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $outsider;

    private User $admin;

    private User $member;

    private User $viewer;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outsider = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->member = User::factory()->create();
        $this->viewer = User::factory()->create();

        $this->project = Project::factory()
            ->withMember($this->admin, ProjectRole::Admin)
            ->withMember($this->member, ProjectRole::Member)
            ->withMember($this->viewer, ProjectRole::Viewer)
            ->create(['key' => 'PROJ', 'name' => '共有プロジェクト']);
    }

    // --- 所属していない人 -------------------------------------------------

    public function test_所属していない人は設定画面を開けない(): void
    {
        $this->actingAs($this->outsider)
            ->get(route('projects.edit', $this->project))
            ->assertForbidden();
    }

    public function test_所属していない人は更新できない(): void
    {
        $this->actingAs($this->outsider)->put(route('projects.update', $this->project), [
            'key' => 'HACK',
            'name' => '乗っ取り',
        ])->assertForbidden();

        $this->assertNotSame('乗っ取り', $this->project->refresh()->name);
    }

    public function test_所属していない人は削除できない(): void
    {
        $this->actingAs($this->outsider)
            ->delete(route('projects.destroy', $this->project))
            ->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $this->project->id]);
    }

    public function test_所属していない人はメンバーを追加できない(): void
    {
        $this->actingAs($this->outsider)->post(route('projects.members.store', $this->project), [
            'email' => $this->outsider->email,
            'role' => ProjectRole::Admin->value,
        ])->assertForbidden();

        $this->assertDatabaseMissing('project_members', [
            'project_id' => $this->project->id,
            'user_id' => $this->outsider->id,
        ]);
    }

    public function test_所属していない人の一覧にはそのプロジェクトが出ない(): void
    {
        $this->actingAs($this->outsider)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertDontSee($this->project->name);
    }

    // --- viewer / member --------------------------------------------------

    public function test_閲覧者は設定画面を開ける(): void
    {
        $this->actingAs($this->viewer)
            ->get(route('projects.edit', $this->project))
            ->assertOk()
            ->assertSee($this->project->name);
    }

    public function test_閲覧者は更新できない(): void
    {
        $this->actingAs($this->viewer)->put(route('projects.update', $this->project), [
            'key' => 'PROJ',
            'name' => '閲覧者による変更',
        ])->assertForbidden();

        $this->assertNotSame('閲覧者による変更', $this->project->refresh()->name);
    }

    public function test_一般メンバーは更新も削除もできない(): void
    {
        $this->actingAs($this->member)->put(route('projects.update', $this->project), [
            'key' => 'PROJ',
            'name' => 'メンバーによる変更',
        ])->assertForbidden();

        $this->actingAs($this->member)
            ->delete(route('projects.destroy', $this->project))
            ->assertForbidden();

        $this->assertNotSame('メンバーによる変更', $this->project->refresh()->name);
        $this->assertDatabaseHas('projects', ['id' => $this->project->id]);
    }

    public function test_一般メンバーはメンバーを追加できない(): void
    {
        $this->actingAs($this->member)->post(route('projects.members.store', $this->project), [
            'email' => $this->outsider->email,
            'role' => ProjectRole::Member->value,
        ])->assertForbidden();
    }

    public function test_管理者以外には設定フォームも削除ボタンも出ない(): void
    {
        $this->actingAs($this->member)
            ->get(route('projects.edit', $this->project))
            ->assertOk()
            ->assertDontSee(route('projects.update', $this->project))
            ->assertDontSee(route('projects.destroy', $this->project));
    }

    // --- admin ------------------------------------------------------------

    public function test_管理者は更新できる(): void
    {
        $this->actingAs($this->admin)->put(route('projects.update', $this->project), [
            'key' => 'PROJ',
            'name' => '管理者による変更',
        ])->assertSessionHasNoErrors();

        $this->assertSame('管理者による変更', $this->project->refresh()->name);
    }
}
