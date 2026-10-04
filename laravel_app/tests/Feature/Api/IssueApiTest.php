<?php

namespace Tests\Feature\Api;

use App\Enums\ProjectRole;
use App\Enums\StatusCategory;
use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Notifications\IssueAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * REST API（/api/v1）。
 *
 * 画面と同じ認可・同じワークフローを通ることと、トークンの権限（read / write）が効くことを確かめる。
 */
class IssueApiTest extends TestCase
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

        $this->owner = User::factory()->create(['name' => '管理者', 'email' => 'owner@example.com']);
        $this->member = User::factory()->create(['name' => 'メンバー', 'email' => 'member@example.com']);
        $this->viewer = User::factory()->create();
        $this->project = Project::personalFor($this->owner);
        $this->project->members()->create(['user_id' => $this->member->id, 'role' => ProjectRole::Member]);
        $this->project->members()->create(['user_id' => $this->viewer->id, 'role' => ProjectRole::Viewer]);

        $this->issue = Issue::factory()
            ->inProject($this->project, $this->owner)
            ->inCategory(StatusCategory::Todo)
            ->create(['title' => '既存の課題', 'priority' => TaskPriority::Medium]);
    }

    private function named(string $name)
    {
        return $this->project->statuses()->where('name', $name)->sole();
    }

    public function test_トークンが無ければ_401_を_JSON_で返す(): void
    {
        $this->get('/api/v1/me')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_本物のトークンで_me_を呼べる(): void
    {
        $token = $this->member->createToken('cli', ['read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'メンバー')
            ->assertJsonPath('data.email', 'member@example.com')
            ->assertJsonPath('token.abilities', ['read']);
    }

    public function test_期限切れのトークンは使えない(): void
    {
        $token = $this->member->createToken('old', ['read'], now()->subDay())->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_参加しているプロジェクトとステータスを返す(): void
    {
        Sanctum::actingAs($this->member, ['read']);

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonPath('data.0.key', $this->project->key)
            ->assertJsonPath('data.0.role', 'member');

        $this->getJson("/api/v1/projects/{$this->project->key}")
            ->assertOk()
            ->assertJsonPath('data.statuses.0.name', '未着手');
    }

    public function test_参加していないプロジェクトは_404(): void
    {
        $other = Project::personalFor(User::factory()->create());
        Sanctum::actingAs($this->member, ['read']);

        $this->getJson("/api/v1/projects/{$other->key}")->assertNotFound();
    }

    public function test_課題の一覧は画面と同じ条件式で絞れる(): void
    {
        Issue::factory()->inProject($this->project, $this->owner)->create(['title' => '急ぎ', 'priority' => TaskPriority::High]);
        Issue::factory()->forUser(User::factory()->create())->create(['title' => '他人の課題']);
        Sanctum::actingAs($this->member, ['read']);

        $this->getJson('/api/v1/issues?q='.urlencode('priority:high'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '急ぎ')
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'total']]);

        $titles = collect($this->getJson('/api/v1/issues')->json('data'))->pluck('title');
        $this->assertNotContains('他人の課題', $titles);
    }

    public function test_読めない条件式は_422(): void
    {
        Sanctum::actingAs($this->member, ['read']);

        $this->getJson('/api/v1/issues?q=foo:bar')->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    public function test_課題をキーで取れて担当者のメールは出さない(): void
    {
        Sanctum::actingAs($this->member, ['read']);

        $this->getJson("/api/v1/issues/{$this->issue->key()}")
            ->assertOk()
            ->assertJsonPath('data.key', $this->issue->key())
            ->assertJsonPath('data.status.name', '未着手')
            ->assertJsonPath('data.assignee.name', '管理者')
            ->assertJsonMissingPath('data.assignee.email');
    }

    public function test_見えない課題は_404(): void
    {
        $foreign = Issue::factory()->forUser(User::factory()->create())->create();
        Sanctum::actingAs($this->member, ['read']);

        $this->getJson("/api/v1/issues/{$foreign->key()}")->assertNotFound();
    }

    public function test_課題を作れる(): void
    {
        Sanctum::actingAs($this->member, ['read', 'write']);

        $response = $this->postJson("/api/v1/projects/{$this->project->key}/issues", [
            'title' => 'API から作成',
            'type' => 'bug',
            'priority' => 'high',
            'status' => '進行中',
            'assignee_id' => $this->owner->id,
            'due_date' => '2026-12-01',
            'estimate_minutes' => 90,
        ])->assertCreated();

        $issue = Issue::where('title', 'API から作成')->sole();
        $response->assertJsonPath('data.key', $issue->key());
        $this->assertSame('進行中', $issue->status->name);
        $this->assertSame($this->member->id, $issue->reporter_id);
        $this->assertSame($this->owner->id, $issue->assignee_id);
        $this->assertSame(90, $issue->original_estimate_minutes);
    }

    public function test_read_だけのトークンでは書けない(): void
    {
        Sanctum::actingAs($this->member, ['read']);

        $this->postJson("/api/v1/projects/{$this->project->key}/issues", ['title' => 'x'])->assertForbidden();
        $this->patchJson("/api/v1/issues/{$this->issue->key()}", ['title' => 'x'])->assertForbidden();
    }

    public function test_閲覧者は_write_トークンでも書けない(): void
    {
        Sanctum::actingAs($this->viewer, ['read', 'write']);

        $this->postJson("/api/v1/projects/{$this->project->key}/issues", ['title' => 'x'])->assertForbidden();
    }

    public function test_送った項目だけを更新し履歴と通知も画面と同じく残る(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->owner, ['read', 'write']);

        $this->patchJson("/api/v1/issues/{$this->issue->key()}", [
            'priority' => 'low',
            'status' => $this->named('進行中')->id,
            'assignee_id' => $this->member->id,
        ])->assertOk()
            ->assertJsonPath('data.title', '既存の課題')
            ->assertJsonPath('data.priority', 'low')
            ->assertJsonPath('data.status.name', '進行中')
            ->assertJsonPath('data.assignee.name', 'メンバー');

        $this->assertSame(1, $this->issue->activities()->where('field', 'status')->where('user_id', $this->owner->id)->count());
        Notification::assertSentTo($this->member, IssueAssignedNotification::class);
    }

    public function test_ワークフローで許可されていない遷移は_422(): void
    {
        Sanctum::actingAs($this->owner, ['read', 'write']);

        $this->patchJson("/api/v1/issues/{$this->issue->key()}", ['status' => 'レビュー中'])
            ->assertUnprocessable()
            ->assertJsonPath('from', '未着手')
            ->assertJsonPath('to', 'レビュー中');

        $this->assertSame('未着手', $this->issue->fresh()->status->name);
    }

    public function test_存在しないステータスとメンバー以外の担当者は_422(): void
    {
        Sanctum::actingAs($this->owner, ['read', 'write']);

        $this->patchJson("/api/v1/issues/{$this->issue->key()}", [
            'status' => 'ありえない',
            'assignee_id' => User::factory()->create()->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['status', 'assignee_id']);
    }

    public function test_削除は画面と同じ権限(): void
    {
        Sanctum::actingAs($this->member, ['read', 'write']);
        $this->deleteJson("/api/v1/issues/{$this->issue->key()}")->assertForbidden();

        Sanctum::actingAs($this->owner, ['read', 'write']);
        $this->deleteJson("/api/v1/issues/{$this->issue->key()}")->assertNoContent();
        $this->assertSoftDeleted($this->issue);
    }

    public function test_コメントを読み書きできサニタイズされる(): void
    {
        Sanctum::actingAs($this->member, ['read', 'write']);

        $this->postJson("/api/v1/issues/{$this->issue->key()}/comments", [
            'body' => '<p>見ました<script>alert(1)</script></p>',
        ])->assertCreated()->assertJsonPath('data.body', '<p>見ました</p>');

        $this->getJson("/api/v1/issues/{$this->issue->key()}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.body_text', '見ました');
    }

    public function test_画面のログインセッションでは_API_を呼べない(): void
    {
        $this->actingAs($this->member)->getJson('/api/v1/me')->assertUnauthorized();
    }
}
