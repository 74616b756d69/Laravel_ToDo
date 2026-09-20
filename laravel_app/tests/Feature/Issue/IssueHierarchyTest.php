<?php

namespace Tests\Feature\Issue;

use App\Enums\IssueType;
use App\Enums\ProjectRole;
use App\Enums\StatusCategory;
use App\Exceptions\IssueHierarchyException;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Services\IssueHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 既存の課題をサブタスクとして引き込む。
 *
 * 「サブタスクかどうか」は parent_id だけで決まり、
 * issue_type（Bug / Story / Task）は引き込んでも変わらない。
 */
class IssueHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Issue $parent;

    private IssueHierarchyService $hierarchy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::personalFor($this->user);
        $this->parent = $this->issue(['title' => '親課題']);
        $this->hierarchy = app(IssueHierarchyService::class);
    }

    private function issue(array $attributes = []): Issue
    {
        return Issue::factory()
            ->inProject($this->project, $this->user)
            ->inCategory(StatusCategory::Todo)
            ->create($attributes);
    }

    private function add(string $input, ?Issue $parent = null)
    {
        return $this->actingAs($this->user)
            ->from(route('tasks.show', $parent ?? $this->parent))
            ->post(route('subtasks.store', $parent ?? $this->parent), ['title' => $input]);
    }

    // --- 課題キーで紐づける ----------------------------------------------------

    public function test_課題キーを貼ると既存課題がサブタスクになる(): void
    {
        $child = $this->issue(['title' => '既存の課題', 'issue_type' => IssueType::Bug]);

        $this->add($child->key())->assertRedirect()->assertSessionHasNoErrors();

        $child->refresh();
        $this->assertSame($this->parent->id, $child->parent_id);
        // 種別は引き込んでも変わらない
        $this->assertSame(IssueType::Bug, $child->issue_type);
    }

    public function test_課題キーは小文字でも引ける(): void
    {
        $child = $this->issue();

        $this->add(strtolower($child->key()))->assertSessionHasNoErrors();

        $this->assertSame($this->parent->id, $child->refresh()->parent_id);
    }

    public function test_前後に空白があっても引ける(): void
    {
        $child = $this->issue();

        $this->add('  '.$child->key().'  ')->assertSessionHasNoErrors();

        $this->assertSame($this->parent->id, $child->refresh()->parent_id);
    }

    // --- URL で紐づける --------------------------------------------------------

    public function test_詳細画面のURLを貼っても引ける(): void
    {
        $child = $this->issue();

        $this->add(route('tasks.show', $child))->assertSessionHasNoErrors();

        $this->assertSame($this->parent->id, $child->refresh()->parent_id);
    }

    public function test_フラグメントつきのURLでも引ける(): void
    {
        $child = $this->issue();

        $this->add(route('tasks.show', $child).'?tab=comments#note')->assertSessionHasNoErrors();

        $this->assertSame($this->parent->id, $child->refresh()->parent_id);
    }

    // --- 新規作成との切り分け ---------------------------------------------------

    public function test_ただの文字列はこれまでどおり新規作成になる(): void
    {
        $this->add('資料を集める')->assertSessionHasNoErrors();

        $child = $this->parent->children()->sole();

        $this->assertSame('資料を集める', $child->title);
        $this->assertSame(IssueType::Subtask, $child->issue_type);
        $this->assertTrue($child->wasRecentlyCreated || $child->exists);
    }

    /**
     * 打ち間違えたキーを、黙ってタイトルとして課題にしない。
     */
    public function test_存在しないキーは新規作成に倒さずエラーにする(): void
    {
        $this->add($this->project->key.'-9999')
            ->assertRedirect()
            ->assertSessionHasErrors('title');

        $this->assertSame(0, $this->parent->children()->count());
    }

    public function test_他プロジェクトのキーは引けない(): void
    {
        $other = Project::factory()->withMember($this->user)->create(['key' => 'OTHER']);
        $foreign = Issue::factory()->inProject($other, $this->user)->create();

        $this->add($foreign->key())->assertSessionHasErrors('title');

        $this->assertNull($foreign->refresh()->parent_id);
    }

    public function test_他プロジェクトの課題はURLでも引けない(): void
    {
        $other = Project::factory()->withMember($this->user)->create(['key' => 'OTHER']);
        $foreign = Issue::factory()->inProject($other, $this->user)->create();

        $this->add(route('tasks.show', $foreign))->assertSessionHasErrors('title');

        $this->assertNull($foreign->refresh()->parent_id);
    }

    // --- 循環と深さ -----------------------------------------------------------

    public function test_自分自身はサブタスクにできない(): void
    {
        $this->add($this->parent->key())->assertSessionHasErrors('title');

        $this->assertNull($this->parent->refresh()->parent_id);
    }

    public function test_すでに子を持つ課題は子にできない(): void
    {
        $middle = $this->issue(['title' => '中間']);
        $this->hierarchy->attach($middle, $this->issue());

        // middle を親の下に入れると孫ができてしまう
        $this->add($middle->key())->assertSessionHasErrors('title');

        $this->assertNull($middle->refresh()->parent_id);
    }

    public function test_すでに子である課題は親になれない(): void
    {
        $child = $this->issue();
        $this->hierarchy->attach($this->parent, $child);

        // child の下にさらに入れようとする
        $this->add($this->issue()->key(), parent: $child)->assertSessionHasErrors('title');

        $this->assertSame(0, $child->children()->count());
    }

    public function test_循環は張れない(): void
    {
        $a = $this->issue(['title' => 'A']);
        $b = $this->issue(['title' => 'B']);

        $this->hierarchy->attach($a, $b);

        // B → A を張ろうとする（A は子を持っているので親になれない）
        $this->expectException(IssueHierarchyException::class);
        $this->hierarchy->attach($b, $a);
    }

    public function test_同じ親に二重で紐づけない(): void
    {
        $child = $this->issue();
        $this->hierarchy->attach($this->parent, $child);

        $this->add($child->key())->assertSessionHasErrors('title');

        $this->assertSame(1, $this->parent->children()->count());
    }

    public function test_別の親へ付け替えられる(): void
    {
        $another = $this->issue(['title' => 'もう一つの親']);
        $child = $this->issue();

        $this->hierarchy->attach($this->parent, $child);
        $this->hierarchy->attach($another, $child);

        $this->assertSame($another->id, $child->refresh()->parent_id);
        $this->assertSame(0, $this->parent->children()->count());
    }

    // --- 一覧からの出入り -------------------------------------------------------

    public function test_引き込んだ課題は一覧とボードから消える(): void
    {
        $child = $this->issue(['title' => '引き込まれる課題']);

        $this->actingAs($this->user)->get(route('tasks.index'))->assertSee('引き込まれる課題');

        $this->hierarchy->attach($this->parent, $child);

        $this->actingAs($this->user)->get(route('tasks.index'))->assertDontSee('引き込まれる課題');
        $this->actingAs($this->user)->get(route('board'))->assertDontSee('引き込まれる課題');
        $this->actingAs($this->user)->get(route('backlog'))->assertDontSee('引き込まれる課題');
    }

    public function test_外すと一覧に戻る(): void
    {
        $child = $this->issue(['title' => '戻ってくる課題']);
        $this->hierarchy->attach($this->parent, $child);

        $this->actingAs($this->user)
            ->patch(route('subtasks.detach', [$this->parent, $child]))
            ->assertRedirect();

        $this->assertNull($child->refresh()->parent_id);
        $this->actingAs($this->user)->get(route('tasks.index'))->assertSee('戻ってくる課題');
    }

    public function test_外しても課題は消えない(): void
    {
        $child = $this->issue();
        $this->hierarchy->attach($this->parent, $child);

        $this->actingAs($this->user)->patch(route('subtasks.detach', [$this->parent, $child]));

        $this->assertNotSoftDeleted($child);
    }

    /**
     * 引き込んだ課題を「外す」つもりで消せてしまわないこと。
     */
    public function test_引き込んだ既存課題は削除できない(): void
    {
        $child = $this->issue(['issue_type' => IssueType::Bug]);
        $this->hierarchy->attach($this->parent, $child);

        $this->actingAs($this->user)
            ->delete(route('subtasks.destroy', [$this->parent, $child]))
            ->assertStatus(422);

        $this->assertNotSoftDeleted($child);
    }

    public function test_ここで作ったサブタスクは削除できる(): void
    {
        $this->add('消される子');
        $child = $this->parent->children()->sole();

        $this->actingAs($this->user)
            ->delete(route('subtasks.destroy', [$this->parent, $child]))
            ->assertRedirect();

        $this->assertDatabaseMissing('tasks', ['id' => $child->id]);
    }

    // --- 引き込んだ課題が持っていたもの -------------------------------------------

    public function test_スプリントやポイントは引き込んでも残る(): void
    {
        $sprint = Sprint::factory()->for($this->project)->create();
        $child = $this->issue(['story_points' => 5]);
        $child->forceFill(['sprint_id' => $sprint->id])->save();

        $this->hierarchy->attach($this->parent, $child);

        $child->refresh();
        $this->assertSame(5, $child->story_points);
        $this->assertSame($sprint->id, $child->sprint_id);
    }

    public function test_進捗率は種別を問わず子の完了で数える(): void
    {
        $this->hierarchy->attach($this->parent, $this->issue(['issue_type' => IssueType::Bug]));
        $this->hierarchy->attach($this->parent, $this->issue());
        $done = $this->issue();
        $this->hierarchy->attach($this->parent, $done);
        app(\App\Services\WorkflowService::class)->toggleCompletion($done);

        $this->assertSame(33, $this->parent->load('children.status')->progress());
    }

    public function test_紐づけは履歴に残らない(): void
    {
        // parent_id は追跡対象外。必要になったら ActivityField に足す
        $child = $this->issue();
        $before = $child->activities()->count();

        $this->hierarchy->attach($this->parent, $child);

        $this->assertSame($before, $child->activities()->count());
    }

    // --- 権限 ---------------------------------------------------------------

    public function test_閲覧者は紐づけられない(): void
    {
        $viewer = User::factory()->create();
        $project = Project::factory()->withMember($viewer, ProjectRole::Viewer)->create();
        $parent = Issue::factory()->inProject($project)->create();
        $child = Issue::factory()->inProject($project)->create();

        $this->actingAs($viewer)
            ->post(route('subtasks.store', $parent), ['title' => $child->key()])
            ->assertForbidden();
    }

    public function test_閲覧者は外せない(): void
    {
        $viewer = User::factory()->create();
        $project = Project::factory()->withMember($viewer, ProjectRole::Viewer)->create();
        $parent = Issue::factory()->inProject($project)->create();
        $child = Issue::factory()->inProject($project)->create();
        $this->hierarchy->attach($parent, $child);

        $this->actingAs($viewer)
            ->patch(route('subtasks.detach', [$parent, $child]))
            ->assertForbidden();
    }

    public function test_別の課題の子は外せない(): void
    {
        $another = $this->issue();
        $child = $this->issue();
        $this->hierarchy->attach($another, $child);

        $this->actingAs($this->user)
            ->patch(route('subtasks.detach', [$this->parent, $child]))
            ->assertNotFound();
    }

    // --- 画面 ---------------------------------------------------------------

    public function test_引き込んだ課題は詳細画面にキーつきで並ぶ(): void
    {
        $child = $this->issue(['title' => '引き込んだ課題', 'issue_type' => IssueType::Bug]);
        $this->hierarchy->attach($this->parent, $child);

        $this->actingAs($this->user)
            ->get(route('tasks.show', $this->parent))
            ->assertOk()
            ->assertSee($child->key())
            ->assertSee('引き込んだ課題')
            ->assertSee(IssueType::Bug->label());
    }

    public function test_子の詳細画面から親へ行ける(): void
    {
        $child = $this->issue();
        $this->hierarchy->attach($this->parent, $child);

        $this->actingAs($this->user)
            ->get(route('tasks.show', $child))
            ->assertOk()
            ->assertSee($this->parent->key())
            ->assertSee(route('tasks.show', $this->parent), false);
    }
}
