<?php

namespace Tests\Feature;

use App\Enums\StatusCategory;
use App\Models\Issue;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * デモデータは「ログインした瞬間に各画面が成立していること」が目的なので、
 * その前提が崩れていないかを検証する。
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private User $demo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoUserSeeder::class);
        $this->demo = User::where('email', config('demo.email'))->firstOrFail();
    }

    /**
     * デモユーザーに見える課題。サブタスクは親に内包されるので数えない。
     *
     * @return \Illuminate\Database\Eloquent\Builder<Issue>
     */
    private function demoIssues(): Builder
    {
        return Issue::query()->visibleTo($this->demo)->topLevel();
    }

    public function test_設定どおりの認証情報でログインできる(): void
    {
        $this->post(route('login'), [
            'email' => config('demo.email'),
            'password' => config('demo.password'),
        ])->assertRedirect(route('tasks.index'));

        $this->assertAuthenticatedAs($this->demo);
    }

    public function test_タスクが100件作られる(): void
    {
        $this->assertSame(100, $this->demoIssues()->count());
    }

    public function test_全カテゴリの課題が存在する(): void
    {
        // ステータス名はプロジェクトごとに違うので、カテゴリで確かめる
        foreach (StatusCategory::cases() as $category) {
            $this->assertTrue(
                $this->demoIssues()
                    ->whereHas('status', fn ($query) => $query->where('category', $category))
                    ->exists(),
                "{$category->label()}の課題が作られていません",
            );
        }
    }

    public function test_タグとサブタスクが紐づいている(): void
    {
        $this->assertSame(6, $this->demo->tags()->count());
        $this->assertTrue($this->demo->tags()->withCount('issues')->get()->every(fn ($tag) => $tag->issues_count > 0));
        $this->assertTrue($this->demoIssues()->has('children')->exists());
    }

    public function test_ダッシュボードの各指標が意味のある値になる(): void
    {
        $response = $this->actingAs($this->demo)->get(route('dashboard'));

        $totals = $response->viewData('totals');
        $this->assertSame(100, $totals['total']);
        $this->assertGreaterThan(0, $totals['done']);
        $this->assertGreaterThan(0, $totals['open']);
        $this->assertGreaterThan(0, $totals['overdue']);

        // 推移グラフが真っ平らにならないこと
        $trend = $response->viewData('trend');
        $this->assertGreaterThanOrEqual(5, $trend->where('count', '>', 0)->count());

        // 連続達成日数が 1 日以上あること
        $this->assertGreaterThanOrEqual(1, $response->viewData('streak'));

        // 優先度の内訳が 1 つに偏っていないこと
        $this->assertSame(3, $response->viewData('byPriority')->where('count', '>', 0)->count());
    }

    public function test_一覧がページ送りされる件数になる(): void
    {
        $tasks = $this->actingAs($this->demo)->get(route('tasks.index'))->viewData('tasks');

        $this->assertSame(100, $tasks->total());
        $this->assertGreaterThan(1, $tasks->lastPage());
    }

    public function test_二重に実行してもデータが増えない(): void
    {
        $this->seed(DemoUserSeeder::class);

        // ユーザーもタスクもタグも積み増されない
        $this->assertSame(1, User::where('email', config('demo.email'))->count());
        $this->assertSame(100, $this->demoIssues()->count());
        $this->assertSame(6, $this->demo->tags()->count());
    }
}
