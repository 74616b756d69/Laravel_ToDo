<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Sprint;
use App\Models\User;
use App\Services\DemoDataRefresher;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * デモデータは日が経っても「シードした日と同じ見え方」を保つ。
 */
class DemoDataRefresherTest extends TestCase
{
    use RefreshDatabase;

    private User $demo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoUserSeeder::class);
        $this->demo = User::where('email', config('demo.email'))->firstOrFail();
    }

    private function overdueCount(): int
    {
        return Issue::query()->visibleTo($this->demo)->overdue()->count();
    }

    public function test_日が経ってもログイン時に期限切れの件数が元に戻る(): void
    {
        $before = $this->overdueCount();

        $this->travel(10)->days();
        $this->assertGreaterThan($before, $this->overdueCount(), '前提: 何もしなければ期限切れが増える');

        $this->post(route('login.demo'));

        $this->assertSame($before, $this->overdueCount());
    }

    public function test_期限とスプリントを同じ日数だけずらす(): void
    {
        $issue = Issue::query()->visibleTo($this->demo)->whereNotNull('due_date')->firstOrFail();
        $sprint = Sprint::where('project_id', $issue->project_id)->orderBy('id')->firstOrFail();

        $this->travel(3)->days();
        $shifted = app(DemoDataRefresher::class)->refresh($this->demo);

        $this->assertSame(3, $shifted);
        $this->assertTrue($issue->fresh()->due_date->equalTo($issue->due_date->copy()->addDays(3)));
        $this->assertTrue($sprint->fresh()->start_date->equalTo($sprint->start_date->copy()->addDays(3)));
    }

    public function test_同じ日に何度ログインしても二重にずらさない(): void
    {
        $this->travel(3)->days();

        $refresher = app(DemoDataRefresher::class);

        $this->assertSame(3, $refresher->refresh($this->demo));
        $this->assertSame(0, $refresher->refresh($this->demo));
    }

    public function test_基準日の記録が無ければ最後の完了日を基準にする(): void
    {
        Cache::forget(DemoDataRefresher::ANCHOR_KEY);

        $this->travel(4)->days();

        $this->assertSame(4, app(DemoDataRefresher::class)->refresh($this->demo));
    }

    public function test_ほかのユーザーの課題には触れない(): void
    {
        $other = Issue::factory()->forUser(User::factory()->create())->create(['due_date' => today()]);

        $this->travel(5)->days();
        app(DemoDataRefresher::class)->refresh($this->demo);

        $this->assertTrue($other->fresh()->due_date->equalTo($other->due_date));
    }
}
