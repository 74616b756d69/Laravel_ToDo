<?php

namespace Tests\Feature\Sprint;

use App\Enums\SprintState;
use App\Exceptions\SprintException;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Services\SprintService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * スプリントの開始と完了。
 *
 * 押さえどころは 2 つ:
 *  - 同時に active にできるのはプロジェクトごとに 1 つだけ
 *  - 完了しても未完了の課題は捨てず、指定した移送先へ必ず送る
 */
class SprintLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private SprintService $sprints;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::personalFor($this->user);
        $this->sprints = app(SprintService::class);
    }

    private function sprint(string $name = 'Sprint 1'): Sprint
    {
        return Sprint::factory()->for($this->project)->create(['name' => $name]);
    }

    private function issueIn(?Sprint $sprint, bool $completed = false): Issue
    {
        $factory = Issue::factory()->inProject($this->project, $this->user);

        $factory = $completed ? $factory->completed() : $factory->inCategory(\App\Enums\StatusCategory::Todo);

        return $factory->create(['sprint_id' => $sprint?->id]);
    }

    // --- 開始 ---------------------------------------------------------------

    public function test_未開始のスプリントを開始できる(): void
    {
        $sprint = $this->sprint();

        $this->actingAs($this->user)
            ->patch(route('sprints.start', $sprint))
            ->assertRedirect();

        $sprint->refresh();
        $this->assertSame(SprintState::Active, $sprint->state);
        // 開始日が未設定なら今日が入る
        $this->assertSame(today()->toDateString(), $sprint->start_date->toDateString());
    }

    public function test_同時にactiveにできるのは1つだけ(): void
    {
        $running = Sprint::factory()->for($this->project)->active()->create(['name' => '進行中']);
        $next = $this->sprint('次の');

        $this->actingAs($this->user)
            ->patch(route('sprints.start', $next))
            ->assertRedirect()
            ->assertSessionHasErrors('sprint');

        $this->assertSame(SprintState::Future, $next->refresh()->state);
        $this->assertSame(SprintState::Active, $running->refresh()->state);
    }

    public function test_同時開始を拒む理由が画面に出る(): void
    {
        Sprint::factory()->for($this->project)->active()->create(['name' => '進行中スプリント']);
        $next = $this->sprint('次の');

        // 進行中のスプリント名を挙げて、何をすればよいかまで伝える
        $this->actingAs($this->user)
            ->patch(route('sprints.start', $next))
            ->assertSessionHasErrors([
                'sprint' => '「進行中スプリント」が進行中です。同時に開始できるスプリントは 1 つだけなので、'
                    .'先に進行中のスプリントを完了してください。',
            ]);

        // 戻ったバックログ画面にその理由が表示される
        $this->followingRedirects()
            ->from(route('backlog'))
            ->actingAs($this->user)
            ->patch(route('sprints.start', $next))
            ->assertOk()
            ->assertSee('「進行中スプリント」が進行中です。');
    }

    /**
     * DB にも「同時 active は 1 つ」の制約が入っていること。
     *
     * アプリ側の確認をすり抜けた場合（並行リクエストなど）の最後の砦。
     */
    public function test_unique制約が二つ目のactiveを弾く(): void
    {
        Sprint::factory()->for($this->project)->active()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('sprints')->insert([
            'project_id' => $this->project->id,
            'name' => '直接差し込んだ進行中',
            'state' => SprintState::Active->value,
            'active_marker' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_別プロジェクトなら同時にactiveにできる(): void
    {
        Sprint::factory()->for($this->project)->active()->create();

        $other = Project::factory()->withMember($this->user)->create();
        $otherSprint = Sprint::factory()->for($other)->create();

        $this->actingAs($this->user)
            ->patch(route('sprints.start', $otherSprint))
            ->assertSessionHasNoErrors();

        $this->assertSame(SprintState::Active, $otherSprint->refresh()->state);
    }

    public function test_進行中のスプリントは開始できない(): void
    {
        $sprint = Sprint::factory()->for($this->project)->active()->create();

        $this->actingAs($this->user)
            ->patch(route('sprints.start', $sprint))
            ->assertSessionHasErrors('sprint');
    }

    public function test_完了したスプリントは開始できない(): void
    {
        $sprint = Sprint::factory()->for($this->project)->closed()->create();

        $this->actingAs($this->user)
            ->patch(route('sprints.start', $sprint))
            ->assertSessionHasErrors('sprint');

        $this->assertSame(SprintState::Closed, $sprint->refresh()->state);
    }

    public function test_完了すれば次を開始できる(): void
    {
        $first = Sprint::factory()->for($this->project)->active()->create(['name' => '1本目']);
        $second = $this->sprint('2本目');

        $this->sprints->complete($first);

        $this->actingAs($this->user)
            ->patch(route('sprints.start', $second))
            ->assertSessionHasNoErrors();

        $this->assertSame(SprintState::Active, $second->refresh()->state);
        // 完了したスプリントは marker を手放している
        $this->assertNull($first->refresh()->active_marker);
    }

    // --- 完了と未完了課題の移送 ------------------------------------------------

    public function test_未完了の課題はバックログへ戻る(): void
    {
        $sprint = Sprint::factory()->for($this->project)->active()->create();

        $done = $this->issueIn($sprint, completed: true);
        $open1 = $this->issueIn($sprint);
        $open2 = $this->issueIn($sprint);

        $this->actingAs($this->user)
            ->post(route('sprints.complete.store', $sprint), ['destination' => ''])
            ->assertRedirect(route('backlog'));

        // 完了した課題は実績としてスプリントに残る
        $this->assertSame($sprint->id, $done->refresh()->sprint_id);
        // 未完了はバックログへ
        $this->assertNull($open1->refresh()->sprint_id);
        $this->assertNull($open2->refresh()->sprint_id);

        $this->assertSame(SprintState::Closed, $sprint->refresh()->state);
    }

    public function test_未完了の課題を次のスプリントへ送れる(): void
    {
        $sprint = Sprint::factory()->for($this->project)->active()->create();
        $next = $this->sprint('次の');

        $done = $this->issueIn($sprint, completed: true);
        $open = $this->issueIn($sprint);

        $this->actingAs($this->user)
            ->post(route('sprints.complete.store', $sprint), ['destination' => $next->id])
            ->assertRedirect(route('backlog'));

        $this->assertSame($sprint->id, $done->refresh()->sprint_id);
        $this->assertSame($next->id, $open->refresh()->sprint_id);
    }

    public function test_移送した件数が通知に出る(): void
    {
        $sprint = Sprint::factory()->for($this->project)->active()->create(['name' => 'Sprint A']);
        $next = $this->sprint('Sprint B');

        $this->issueIn($sprint);
        $this->issueIn($sprint);
        $this->issueIn($sprint, completed: true);

        $this->actingAs($this->user)
            ->post(route('sprints.complete.store', $sprint), ['destination' => $next->id])
            ->assertSessionHas('status', fn (string $message) => str_contains($message, '2 件')
                && str_contains($message, 'Sprint B'));
    }

    public function test_課題が一件も無くても完了できる(): void
    {
        $sprint = Sprint::factory()->for($this->project)->active()->create();

        $this->actingAs($this->user)
            ->post(route('sprints.complete.store', $sprint), ['destination' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame(SprintState::Closed, $sprint->refresh()->state);
    }

    public function test_進行中のスプリントへは送れない(): void
    {
        $sprint = Sprint::factory()->for($this->project)->active()->create();
        $issue = $this->issueIn($sprint);

        // 進行中は 1 つだけなので、自分自身を指定した場合がこれにあたる
        $this->actingAs($this->user)
            ->post(route('sprints.complete.store', $sprint), ['destination' => $sprint->id])
            ->assertSessionHasErrors('destination');

        $this->assertSame($sprint->id, $issue->refresh()->sprint_id);
        $this->assertSame(SprintState::Active, $sprint->refresh()->state);
    }

    public function test_完了したスプリントへは送れない(): void
    {
        $sprint = Sprint::factory()->for($this->project)->active()->create();
        $closed = Sprint::factory()->for($this->project)->closed()->create(['name' => '終わったやつ']);

        $this->actingAs($this->user)
            ->post(route('sprints.complete.store', $sprint), ['destination' => $closed->id])
            ->assertSessionHasErrors('destination');
    }

    public function test_別プロジェクトのスプリントへは送れない(): void
    {
        $sprint = Sprint::factory()->for($this->project)->active()->create();
        $foreign = Sprint::factory()->create(['name' => 'よそのスプリント']);

        $this->actingAs($this->user)
            ->post(route('sprints.complete.store', $sprint), ['destination' => $foreign->id])
            ->assertSessionHasErrors('destination');
    }

    public function test_進行中でないスプリントは完了できない(): void
    {
        $sprint = $this->sprint();

        $this->actingAs($this->user)
            ->post(route('sprints.complete.store', $sprint), ['destination' => ''])
            ->assertSessionHasErrors('sprint');

        $this->assertSame(SprintState::Future, $sprint->refresh()->state);
    }

    public function test_サービスから直接呼んでも制約は守られる(): void
    {
        $sprint = Sprint::factory()->for($this->project)->active()->create();
        $closed = Sprint::factory()->for($this->project)->closed()->create();

        $this->expectException(SprintException::class);

        $this->sprints->complete($sprint, $closed);
    }

    public function test_完了に失敗したらスプリントも課題も動かない(): void
    {
        $sprint = Sprint::factory()->for($this->project)->active()->create();
        $closed = Sprint::factory()->for($this->project)->closed()->create();
        $issue = $this->issueIn($sprint);

        try {
            $this->sprints->complete($sprint, $closed);
        } catch (SprintException) {
            // 握りつぶして状態だけ確かめる
        }

        $this->assertSame(SprintState::Active, $sprint->refresh()->state);
        $this->assertSame($sprint->id, $issue->refresh()->sprint_id);
    }

    // --- 認可が検証より先に走ること -------------------------------------------

    /**
     * 権限の無い相手には、検証結果ではなく 403 を返す。
     *
     * 逆順だと、他プロジェクトのスプリント名が存在するかどうかを
     * unique エラーの有無から、移送先 ID が有効かどうかを
     * exists エラーの有無から推測できてしまう。
     */
    public function test_権限が無ければ検証より先に403を返す(): void
    {
        $outsider = User::factory()->create();
        $sprint = $this->sprint('内緒の名前');
        $active = Sprint::factory()->for($this->project)->active()->create();

        $this->actingAs($outsider)
            ->put(route('sprints.update', $sprint), ['name' => ''])
            ->assertForbidden();

        // 存在する名前を送っても、unique エラーではなく 403
        $this->actingAs($outsider)
            ->put(route('sprints.update', $sprint), ['name' => '内緒の名前'])
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('sprints.complete.store', $active), ['destination' => 999999])
            ->assertForbidden();
    }

    public function test_閲覧者もスプリントを更新できない(): void
    {
        $viewer = User::factory()->create();
        $this->project->members()->create([
            'user_id' => $viewer->id, 'role' => \App\Enums\ProjectRole::Viewer,
        ]);

        $this->actingAs($viewer)
            ->put(route('sprints.update', $this->sprint()), ['name' => '閲覧者による変更'])
            ->assertForbidden();
    }

    // --- 完了確認画面 ---------------------------------------------------------

    public function test_完了画面に移送される課題と選択肢が出る(): void
    {
        $sprint = Sprint::factory()->for($this->project)->active()->create();
        $next = $this->sprint('次のスプリント');
        Sprint::factory()->for($this->project)->closed()->create(['name' => '終わったスプリント']);

        $this->issueIn($sprint, completed: true);
        $open = $this->issueIn($sprint);

        $this->actingAs($this->user)
            ->get(route('sprints.complete', $sprint))
            ->assertOk()
            ->assertSee($open->title)
            ->assertSee('バックログへ戻す')
            ->assertSee('次のスプリント')
            // 完了したスプリントは移送先に出さない
            ->assertDontSee('終わったスプリント');
    }

    public function test_スプリントを消すと課題はバックログへ戻る(): void
    {
        $sprint = Sprint::factory()->for($this->project)->create();
        $issue = $this->issueIn($sprint);

        $this->actingAs($this->user)
            ->delete(route('sprints.destroy', $sprint))
            ->assertRedirect();

        // 外部キーが nullOnDelete なので課題自体は消えない
        $this->assertNull($issue->refresh()->sprint_id);
        $this->assertDatabaseMissing('sprints', ['id' => $sprint->id]);
    }
}
