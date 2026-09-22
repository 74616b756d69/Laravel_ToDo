<?php

namespace Tests\Feature\Issue;

use App\Enums\IssueLinkType;
use App\Enums\IssueType;
use App\Enums\ProjectRole;
use App\Enums\StatusCategory;
use App\Exceptions\IssueLinkException;
use App\Models\Issue;
use App\Models\IssueLink;
use App\Models\Project;
use App\Models\User;
use App\Services\IssueLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * リンクされた作業項目。
 *
 * 親子（サブタスク）と違い、関連づけても相手は一覧・ボード・バックログに残る。
 * 「関係がある」とだけ言いたいときのための機能。
 */
class IssueLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Issue $source;

    private IssueLinkService $links;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::personalFor($this->user);
        $this->source = $this->issue(['title' => '起点の課題']);
        $this->links = app(IssueLinkService::class);
    }

    private function issue(array $attributes = []): Issue
    {
        return Issue::factory()
            ->inProject($this->project, $this->user)
            ->inCategory(StatusCategory::Todo)
            ->create($attributes);
    }

    private function tryLink(string $target, IssueLinkType $type = IssueLinkType::Relates, ?Issue $from = null)
    {
        $from ??= $this->source;

        return $this->actingAs($this->user)
            ->from(route('tasks.show', $from))
            ->postJson(route('links.store', $from), ['target' => $target, 'type' => $type->value]);
    }

    // --- 関連づけ -------------------------------------------------------------

    public function test_課題キーで関連づけられる(): void
    {
        $target = $this->issue(['title' => '相手の課題']);

        $this->actingAs($this->user)
            ->from(route('tasks.show', $this->source))
            ->post(route('links.store', $this->source), [
                'target' => $target->key(),
                'type' => IssueLinkType::Relates->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('issue_links', [
            'source_issue_id' => $this->source->id,
            'target_issue_id' => $target->id,
            'type' => IssueLinkType::Relates->value,
        ]);
    }

    public function test_URLでも関連づけられる(): void
    {
        $target = $this->issue();

        // 成功時はフォーム送信として元の画面へ戻す
        $this->tryLink(route('tasks.show', $target))->assertRedirect();

        $this->assertSame(1, $this->source->outgoingLinks()->count());
    }

    /**
     * ここが親子との一番の違い。関連づけても相手は一覧に残る。
     */
    public function test_関連づけても相手は一覧やボードに残る(): void
    {
        $target = $this->issue(['title' => '残るはずの課題']);

        $this->links->link($this->source, $target, IssueLinkType::Relates);

        $this->actingAs($this->user)->get(route('tasks.index'))->assertSee('残るはずの課題');
        $this->actingAs($this->user)->get(route('board'))->assertSee('残るはずの課題');
        $this->actingAs($this->user)->get(route('backlog'))->assertSee('残るはずの課題');
        $this->assertNull($target->refresh()->parent_id);
    }

    public function test_種別は保たれる(): void
    {
        $target = $this->issue(['issue_type' => IssueType::Bug]);

        $this->links->link($this->source, $target, IssueLinkType::Blocks);

        $this->assertSame(IssueType::Bug, $target->refresh()->issue_type);
    }

    // --- 向き -----------------------------------------------------------------

    public function test_関連は向きが無いので両側で同じ読み方になる(): void
    {
        $target = $this->issue();
        $link = $this->links->link($this->source, $target, IssueLinkType::Relates);

        $this->assertSame('が関連', $link->labelFor($this->source));
        $this->assertSame('が関連', $link->labelFor($target));
    }

    public function test_ブロックは見る側で読み方が変わる(): void
    {
        $target = $this->issue();
        $link = $this->links->link($this->source, $target, IssueLinkType::Blocks);

        $this->assertSame('をブロック', $link->labelFor($this->source));
        $this->assertSame('にブロックされている', $link->labelFor($target));
    }

    public function test_相手の詳細画面にも関連が出る(): void
    {
        $target = $this->issue(['title' => '相手の課題']);
        $this->links->link($this->source, $target, IssueLinkType::Blocks);

        // 張られた側から見ると「にブロックされている」
        $this->actingAs($this->user)
            ->get(route('tasks.show', $target))
            ->assertOk()
            ->assertSee('にブロックされている')
            ->assertSee($this->source->key());
    }

    public function test_読み方ごとにまとまる(): void
    {
        $a = $this->issue();
        $b = $this->issue();
        $c = $this->issue();

        $this->links->link($this->source, $a, IssueLinkType::Relates);
        $this->links->link($this->source, $b, IssueLinkType::Relates);
        $this->links->link($this->source, $c, IssueLinkType::Blocks);

        $grouped = $this->links->groupedFor($this->source->fresh());

        $this->assertSame(['が関連', 'をブロック'], $grouped->keys()->all());
        $this->assertCount(2, $grouped['が関連']);
        $this->assertCount(1, $grouped['をブロック']);
    }

    // --- 拒否する組み合わせ -----------------------------------------------------

    public function test_自分自身とは関連づけられない(): void
    {
        $this->tryLink($this->source->key())->assertStatus(422);

        $this->assertSame(0, $this->source->outgoingLinks()->count());
    }

    public function test_存在しないキーはエラーになる(): void
    {
        $this->tryLink($this->project->key.'-9999')->assertStatus(422);
    }

    public function test_他プロジェクトの課題とは関連づけられない(): void
    {
        $other = Project::factory()->withMember($this->user)->create(['key' => 'OTHER']);
        $foreign = Issue::factory()->inProject($other, $this->user)->create();

        $this->tryLink($foreign->key())->assertStatus(422);
        $this->tryLink(route('tasks.show', $foreign))->assertStatus(422);

        $this->assertSame(0, $this->source->outgoingLinks()->count());
    }

    public function test_同じ関連は二重に張れない(): void
    {
        $target = $this->issue();
        $this->links->link($this->source, $target, IssueLinkType::Relates);

        $this->tryLink($target->key())->assertStatus(422);

        $this->assertSame(1, IssueLink::count());
    }

    /**
     * 「関連」は向きが無いので、逆から張り直しても同じ関係とみなす。
     */
    public function test_関連は逆向きでも二重にならない(): void
    {
        $target = $this->issue();
        $this->links->link($this->source, $target, IssueLinkType::Relates);

        $this->expectException(IssueLinkException::class);
        $this->links->link($target, $this->source, IssueLinkType::Relates);
    }

    /**
     * 向きのある種別は、逆向きなら別の関係として張れる。
     */
    public function test_ブロックは逆向きなら別の関連として張れる(): void
    {
        $target = $this->issue();

        $this->links->link($this->source, $target, IssueLinkType::Blocks);
        $this->links->link($target, $this->source, IssueLinkType::Blocks);

        $this->assertSame(2, IssueLink::count());
    }

    public function test_種類が違えば同じ相手に張れる(): void
    {
        $target = $this->issue();

        $this->links->link($this->source, $target, IssueLinkType::Relates);
        $this->links->link($this->source, $target, IssueLinkType::Blocks);

        $this->assertSame(2, IssueLink::count());
    }

    public function test_未知の種類は受け付けない(): void
    {
        $target = $this->issue();

        $this->actingAs($this->user)
            ->postJson(route('links.store', $this->source), [
                'target' => $target->key(),
                'type' => 'unknown',
            ])
            ->assertStatus(422);
    }

    // --- 外す -----------------------------------------------------------------

    public function test_関連を外せる(): void
    {
        $target = $this->issue();
        $link = $this->links->link($this->source, $target, IssueLinkType::Relates);

        $this->actingAs($this->user)
            ->delete(route('links.destroy', [$this->source, $link]))
            ->assertRedirect();

        $this->assertDatabaseMissing('issue_links', ['id' => $link->id]);
    }

    public function test_相手側からも外せる(): void
    {
        $target = $this->issue();
        $link = $this->links->link($this->source, $target, IssueLinkType::Relates);

        $this->actingAs($this->user)
            ->delete(route('links.destroy', [$target, $link]))
            ->assertRedirect();

        $this->assertDatabaseMissing('issue_links', ['id' => $link->id]);
    }

    public function test_無関係な課題からは外せない(): void
    {
        $target = $this->issue();
        $stranger = $this->issue();
        $link = $this->links->link($this->source, $target, IssueLinkType::Relates);

        $this->actingAs($this->user)
            ->delete(route('links.destroy', [$stranger, $link]))
            ->assertNotFound();

        $this->assertDatabaseHas('issue_links', ['id' => $link->id]);
    }

    public function test_外しても課題は消えない(): void
    {
        $target = $this->issue();
        $link = $this->links->link($this->source, $target, IssueLinkType::Relates);

        $this->actingAs($this->user)->delete(route('links.destroy', [$this->source, $link]));

        $this->assertNotSoftDeleted($target);
    }

    public function test_課題を完全に削除すると関連も消える(): void
    {
        $target = $this->issue();
        $this->links->link($this->source, $target, IssueLinkType::Relates);

        $target->forceDelete();

        $this->assertSame(0, IssueLink::count());
    }

    // --- 権限 -----------------------------------------------------------------

    public function test_閲覧者は関連づけられない(): void
    {
        $viewer = User::factory()->create();
        $project = Project::factory()->withMember($viewer, ProjectRole::Viewer)->create();
        $source = Issue::factory()->inProject($project)->create();
        $target = Issue::factory()->inProject($project)->create();

        $this->actingAs($viewer)
            ->post(route('links.store', $source), [
                'target' => $target->key(), 'type' => IssueLinkType::Relates->value,
            ])
            ->assertForbidden();
    }

    public function test_閲覧者は外せない(): void
    {
        $viewer = User::factory()->create();
        $project = Project::factory()->withMember($viewer, ProjectRole::Viewer)->create();
        $source = Issue::factory()->inProject($project)->create();
        $target = Issue::factory()->inProject($project)->create();
        $link = $this->links->link($source, $target, IssueLinkType::Relates);

        $this->actingAs($viewer)
            ->delete(route('links.destroy', [$source, $link]))
            ->assertForbidden();
    }

    public function test_参加していないプロジェクトの課題には張れない(): void
    {
        $others = Issue::factory()->create();

        $this->actingAs($this->user)
            ->post(route('links.store', $others), [
                'target' => 'X-1', 'type' => IssueLinkType::Relates->value,
            ])
            ->assertNotFound();
    }

    public function test_権限が無ければ検証より先に弾く(): void
    {
        // メンバーではあるが触れない人には 403。入力の不備は見せない
        $viewer = User::factory()->create();
        $this->project->members()->create(['user_id' => $viewer->id, 'role' => ProjectRole::Viewer]);

        $this->actingAs($viewer)
            ->post(route('links.store', $this->source), ['target' => '', 'type' => 'unknown'])
            ->assertForbidden();

        // プロジェクトの外の人には、課題の存在ごと伏せて 404
        $this->actingAs(User::factory()->create())
            ->post(route('links.store', $this->source), ['target' => '', 'type' => 'unknown'])
            ->assertNotFound();
    }

    // --- 画面 -----------------------------------------------------------------

    public function test_行に課題の情報が並ぶ(): void
    {
        $assignee = User::factory()->create(['name' => '担当者太郎']);
        $target = $this->issue(['title' => '関連する課題', 'issue_type' => IssueType::Bug]);
        $target->forceFill(['assignee_id' => $assignee->id])->save();

        $this->links->link($this->source, $target, IssueLinkType::Relates);

        $this->actingAs($this->user)
            ->get(route('tasks.show', $this->source))
            ->assertOk()
            ->assertSee('リンクされた作業項目')
            ->assertSee('が関連')
            ->assertSee($target->key())
            ->assertSee('関連する課題')
            ->assertSee($target->status->name)
            ->assertSee('担当: 担当者太郎')
            ->assertSee('title="バグ"', false)
            // 優先度は記号で出す
            ->assertSee('優先度'.$target->priority->label());
    }

    public function test_関連を増やしてもクエリ数が増えない(): void
    {
        $measure = function (): int {
            $count = 0;
            DB::listen(function () use (&$count) {
                $count++;
            });
            $this->actingAs($this->user)->get(route('tasks.show', $this->source))->assertOk();

            return $count;
        };

        collect(range(1, 2))->each(fn () => $this->links->link($this->source, $this->issue(), IssueLinkType::Relates));
        $few = $measure();

        collect(range(1, 15))->each(fn () => $this->links->link($this->source, $this->issue(), IssueLinkType::Relates));
        $many = $measure();

        $this->assertSame($few, $many, "関連を増やすとクエリが {$few} → {$many} に増えています");
    }

    public function test_関連が無ければ何も並ばない(): void
    {
        $this->actingAs($this->user)
            ->get(route('tasks.show', $this->source))
            ->assertOk()
            ->assertSee('リンクされた作業項目')
            ->assertDontSee('が関連:');
    }
}
