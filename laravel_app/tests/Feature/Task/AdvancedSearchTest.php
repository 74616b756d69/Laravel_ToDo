<?php

namespace Tests\Feature\Task;

use App\Enums\IssueType;
use App\Enums\ProjectRole;
use App\Enums\StatusCategory;
use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Tag;
use App\Models\User;
use App\Support\Search\IssueQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 一覧の条件式（q）。
 *
 * 1 つの条件ごとに「当たるもの」と「当たらないもの」を並べて、絞れていることを確かめる。
 */
class AdvancedSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private User $other;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 10:00:00');

        $this->me = User::factory()->create(['name' => '自分']);
        $this->other = User::factory()->create(['name' => '佐藤 花子']);
        $this->project = Project::personalFor($this->me);
        $this->project->members()->create(['user_id' => $this->other->id, 'role' => ProjectRole::Member]);
    }

    private function issue(string $title, array $attributes = [], StatusCategory $category = StatusCategory::Todo): Issue
    {
        return Issue::factory()
            ->inProject($this->project, $this->me)
            ->inCategory($category)
            ->create(['title' => $title, ...$attributes]);
    }

    private function search(string $q)
    {
        return $this->actingAs($this->me)->get(route('tasks.index', ['q' => $q]))->assertOk();
    }

    public function test_担当者で絞る(): void
    {
        $this->issue('自分の課題', ['assignee_id' => $this->me->id]);
        $this->issue('佐藤さんの課題', ['assignee_id' => $this->other->id]);
        $this->issue('誰のでもない課題', ['assignee_id' => null]);

        $this->search('assignee:me')->assertSee('自分の課題')->assertDontSee('佐藤さんの課題')->assertDontSee('誰のでもない課題');
        $this->search('assignee:佐藤')->assertSee('佐藤さんの課題')->assertDontSee('自分の課題');
        $this->search('assignee:none')->assertSee('誰のでもない課題')->assertDontSee('自分の課題');
        $this->search('is:unassigned')->assertSee('誰のでもない課題')->assertDontSee('自分の課題');
    }

    public function test_ステータス名と状態で絞る(): void
    {
        $this->issue('まだの課題');
        $this->issue('作業中の課題', category: StatusCategory::InProgress);
        $this->issue('済んだ課題', category: StatusCategory::Done);

        $this->search('status:進行中')->assertSee('作業中の課題')->assertDontSee('まだの課題');
        $this->search('ステータス:"進行中"')->assertSee('作業中の課題');
        $this->search('is:done')->assertSee('済んだ課題')->assertDontSee('作業中の課題');
        $this->search('is:open')->assertSee('作業中の課題')->assertSee('まだの課題')->assertDontSee('済んだ課題');
    }

    public function test_優先度とタイプは英語でも日本語でも書ける(): void
    {
        $this->issue('急ぎのバグ', ['priority' => TaskPriority::High, 'issue_type' => IssueType::Bug]);
        $this->issue('のんびりタスク', ['priority' => TaskPriority::Low, 'issue_type' => IssueType::Task]);

        $this->search('priority:high')->assertSee('急ぎのバグ')->assertDontSee('のんびりタスク');
        $this->search('優先度:低')->assertSee('のんびりタスク')->assertDontSee('急ぎのバグ');
        $this->search('type:バグ')->assertSee('急ぎのバグ')->assertDontSee('のんびりタスク');
    }

    public function test_期限を相対日数と日付で比べる(): void
    {
        $this->issue('明後日まで', ['due_date' => '2026-10-07']);
        $this->issue('来月まで', ['due_date' => '2026-11-20']);
        $this->issue('期限なし', ['due_date' => null]);

        $this->search('due<7d')->assertSee('明後日まで')->assertDontSee('来月まで')->assertDontSee('期限なし');
        $this->search('due>=2026-11-01')->assertSee('来月まで')->assertDontSee('明後日まで');
        $this->search('due:2026-10-07')->assertSee('明後日まで')->assertDontSee('来月まで');
        $this->search('due:none')->assertSee('期限なし')->assertDontSee('明後日まで');
    }

    public function test_作成日を過去の日数で比べる(): void
    {
        $this->travelTo('2026-09-01 10:00:00');
        $this->issue('先月の課題');
        $this->travelTo('2026-10-04 10:00:00');
        $this->issue('昨日の課題');
        $this->travelTo('2026-10-05 10:00:00');

        $this->search('created>=-7d')->assertSee('昨日の課題')->assertDontSee('先月の課題');
    }

    public function test_否定とキーワードを組み合わせる(): void
    {
        $later = Tag::factory()->for($this->me)->create(['name' => '後回し']);
        $this->issue('ログイン画面の修正');
        $this->issue('ログインAPIの修正')->tags()->attach($later);
        $this->issue('検索の改善');

        $this->search('ログイン -tag:後回し')
            ->assertSee('ログイン画面の修正')
            ->assertDontSee('ログインAPIの修正')
            ->assertDontSee('検索の改善');
    }

    public function test_スプリントで絞る(): void
    {
        $sprint = Sprint::factory()->for($this->project)->active()->create(['name' => 'スプリント 7']);
        $this->issue('今のスプリント')->forceFill(['sprint_id' => $sprint->id])->save();
        $this->issue('バックログの課題');

        $this->search('sprint:current')->assertSee('今のスプリント')->assertDontSee('バックログの課題');
        $this->search('sprint:none')->assertSee('バックログの課題')->assertDontSee('今のスプリント');
        $this->search('sprint:"スプリント 7"')->assertSee('今のスプリント');
    }

    public function test_ウォッチ中で絞る(): void
    {
        $this->issue('見ている課題');
        // 起票者は自動でウォッチに入るので、外したものを用意する
        $this->issue('見ていない課題')->unwatch($this->me);

        $this->search('is:watching')->assertSee('見ている課題')->assertDontSee('見ていない課題');
    }

    public function test_読めない条件は外して理由を出す(): void
    {
        $this->issue('自分の課題', ['assignee_id' => $this->me->id]);

        $this->search('asignee:me due<そのうち')
            ->assertSee('条件式の一部を読めなかった')
            ->assertSee('「asignee」という項目はありません。')
            ->assertSee('日付「そのうち」が読めません', false)
            ->assertSee('自分の課題');
    }

    public function test_条件式でも見えない課題は出ない(): void
    {
        $stranger = User::factory()->create();
        Issue::factory()->forUser($stranger)->create(['title' => '他人の秘密の課題']);

        $this->search('is:open')->assertDontSee('他人の秘密の課題');
        $this->search('project:'.Project::personalFor($stranger)->key)->assertDontSee('他人の秘密の課題');
    }

    public function test_ヘッダーの検索窓は条件式を一覧の条件検索へ流す(): void
    {
        $this->actingAs($this->me)->get(route('search', ['q' => 'assignee:me']))
            ->assertRedirect(route('tasks.index', ['q' => 'assignee:me']));

        $this->actingAs($this->me)->get(route('search', ['q' => 'ログイン']))
            ->assertRedirect(route('tasks.index', ['keyword' => 'ログイン']));
    }

    public function test_URL_や時刻は条件ではなく言葉として扱う(): void
    {
        $query = IssueQuery::parse('https://example.com/a 10:30');

        $this->assertSame([], $query->errors());
        $this->assertFalse(IssueQuery::looksLikeQuery('https://example.com/a'));
    }

    public function test_存在しない日付は読まない(): void
    {
        $this->assertNotSame([], IssueQuery::parse('due<2026-02-31')->errors());
        $this->assertSame([], IssueQuery::parse('due<2026-02-28')->errors());
    }

    public function test_比較できない項目に大小は使えない(): void
    {
        $this->assertSame(
            ['priority>high: 「priority」には大小の比較（< >）が使えません。'],
            IssueQuery::parse('priority>high')->errors(),
        );
    }
}
