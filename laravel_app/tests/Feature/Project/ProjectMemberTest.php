<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectMemberTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->project = Project::factory()->withMember($this->admin, ProjectRole::Admin)->create();
    }

    public function test_登録済みのユーザーをメールアドレスで追加できる(): void
    {
        $invitee = User::factory()->create(['email' => 'teammate@example.com']);

        $this->actingAs($this->admin)->post(route('projects.members.store', $this->project), [
            'email' => 'teammate@example.com',
            'role' => ProjectRole::Viewer->value,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('project_members', [
            'project_id' => $this->project->id,
            'user_id' => $invitee->id,
            'role' => ProjectRole::Viewer->value,
        ]);
    }

    public function test_未登録のメールアドレスは追加できない(): void
    {
        $this->actingAs($this->admin)->post(route('projects.members.store', $this->project), [
            'email' => 'unknown@example.com',
            'role' => ProjectRole::Member->value,
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, $this->project->members()->count());
    }

    public function test_すでに参加しているユーザーは追加できない(): void
    {
        $this->actingAs($this->admin)->post(route('projects.members.store', $this->project), [
            'email' => $this->admin->email,
            'role' => ProjectRole::Member->value,
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, $this->project->members()->count());
    }

    public function test_役割は定義済みの値しか受け付けない(): void
    {
        User::factory()->create(['email' => 'teammate@example.com']);

        $this->actingAs($this->admin)->post(route('projects.members.store', $this->project), [
            'email' => 'teammate@example.com',
            'role' => 'owner',
        ])->assertSessionHasErrors('role');
    }

    public function test_追加されたメンバーの一覧にプロジェクトが出る(): void
    {
        $invitee = User::factory()->create(['email' => 'teammate@example.com']);

        $this->actingAs($this->admin)->post(route('projects.members.store', $this->project), [
            'email' => 'teammate@example.com',
            'role' => ProjectRole::Member->value,
        ]);

        $this->actingAs($invitee)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee($this->project->name);
    }

    public function test_設定画面にメンバーと役割が並ぶ(): void
    {
        $viewer = User::factory()->create(['name' => '閲覧太郎']);
        $this->project->members()->create(['user_id' => $viewer->id, 'role' => ProjectRole::Viewer]);

        $this->actingAs($this->admin)
            ->get(route('projects.edit', $this->project))
            ->assertOk()
            ->assertSee($this->admin->name)
            ->assertSee('閲覧太郎')
            ->assertSee(ProjectRole::Viewer->label());
    }
}
