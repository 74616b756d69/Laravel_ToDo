<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_プロジェクトを作成すると作成者が管理者として参加する(): void
    {
        $this->actingAs($this->user)->post(route('projects.store'), [
            'key' => 'PROJ',
            'name' => 'ポートフォリオ刷新',
            'description' => '説明',
        ])->assertRedirect();

        $project = Project::where('key', 'PROJ')->sole();

        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $this->user->id,
            'role' => ProjectRole::Admin->value,
        ]);
    }

    public function test_プロジェクト作成時に個人用の組織が自動で作られる(): void
    {
        $this->actingAs($this->user)->post(route('projects.store'), [
            'key' => 'PROJ',
            'name' => 'ポートフォリオ刷新',
        ])->assertRedirect();

        $this->assertDatabaseHas('organizations', ['owner_id' => $this->user->id]);
        $this->assertSame(1, $this->user->ownedOrganizations()->count());
    }

    public function test_二つ目のプロジェクトは同じ組織にぶら下がる(): void
    {
        $this->actingAs($this->user)->post(route('projects.store'), ['key' => 'AAA', 'name' => '一つ目']);
        $this->actingAs($this->user)->post(route('projects.store'), ['key' => 'BBB', 'name' => '二つ目']);

        $this->assertSame(1, $this->user->ownedOrganizations()->count());
        $this->assertSame(
            Project::where('key', 'AAA')->value('organization_id'),
            Project::where('key', 'BBB')->value('organization_id'),
        );
    }

    public function test_課題キーは小文字で入力しても大文字で保存される(): void
    {
        $this->actingAs($this->user)
            ->post(route('projects.store'), ['key' => 'proj', 'name' => 'テスト'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('projects', ['key' => 'PROJ']);
    }

    public function test_課題キーの形式を検証する(): void
    {
        $invalid = [
            'A',            // 短すぎる
            'ABCDEFGHIJK',  // 長すぎる
            'PROJ1',        // 数字は混ぜられない
            'PRO-J',        // 記号は混ぜられない
            'プロジェクト',   // 英字以外は使えない
        ];

        foreach ($invalid as $key) {
            $this->actingAs($this->user)
                ->post(route('projects.store'), ['key' => $key, 'name' => 'テスト'])
                ->assertSessionHasErrors('key');
        }

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_課題キーは重複させられない(): void
    {
        Project::factory()->create(['key' => 'PROJ']);

        $this->actingAs($this->user)
            ->post(route('projects.store'), ['key' => 'PROJ', 'name' => 'テスト'])
            ->assertSessionHasErrors('key');
    }

    public function test_自分の課題キーは変えずに更新できる(): void
    {
        $project = Project::factory()->withMember($this->user)->create(['key' => 'PROJ']);

        $this->actingAs($this->user)->put(route('projects.update', $project), [
            'key' => 'PROJ',
            'name' => '名前だけ変更',
        ])->assertSessionHasNoErrors();

        $this->assertSame('名前だけ変更', $project->refresh()->name);
    }

    public function test_一覧には自分が参加しているプロジェクトだけが出る(): void
    {
        $mine = Project::factory()->withMember($this->user)->create(['name' => '自分のプロジェクト']);
        $others = Project::factory()->create(['name' => '他人のプロジェクト']);

        $this->actingAs($this->user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee($mine->name)
            ->assertDontSee($others->name);
    }

    public function test_管理者はプロジェクトを削除できる(): void
    {
        $project = Project::factory()->withMember($this->user)->create();

        $this->actingAs($this->user)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_未ログインではプロジェクト一覧を開けない(): void
    {
        $this->get(route('projects.index'))->assertRedirect(route('login'));
    }
}
