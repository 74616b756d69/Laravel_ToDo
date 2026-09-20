<?php

namespace Tests\Unit;

use App\Enums\StatusCategory;
use App\Exceptions\IllegalTransitionException;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Status;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * ワークフローの遷移マトリクスを網羅する。
 *
 * 既定のワークフロー（To Do / In Progress / In Review / Done）の
 * 4 × 4 = 16 通りすべてについて、許可・不許可を 1 件ずつ確かめる。
 * 表を書き換えたらここが落ちるので、意図しない緩和に気づける。
 */
class WorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowService $workflows;

    private Project $project;

    /** @var Collection<string, Status> */
    private Collection $statuses;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflows = app(WorkflowService::class);
        // Project の created フックが既定のワークフローを入れる
        $this->project = Project::factory()->create();
        $this->statuses = $this->project->statuses()->get()->keyBy('name');
    }

    private function named(string $name): Status
    {
        return $this->statuses[$name];
    }

    private function issueAt(string $statusName): Issue
    {
        return Issue::factory()->inStatus($this->named($statusName))->create();
    }

    /**
     * 既定ワークフローの遷移マトリクス。
     *
     * 行が遷移元、列が遷移先。true が許可。
     * 同じステータスへの「移動」は遷移ではないので常に許可（レーン内の並べ替え）。
     *
     * 禁止しているのは 2 本だけ:
     *  - To Do → In Review … 着手していないものをレビューに出せない
     *  - Done  → In Review … 完了したものをレビューに戻せない
     *
     * @return array<string, array<string, bool>>
     */
    public static function matrix(): array
    {
        return [
            //            To Do  In Progress  In Review  Done
            'To Do' => ['To Do' => true, 'In Progress' => true, 'In Review' => false, 'Done' => true],
            'In Progress' => ['To Do' => true, 'In Progress' => true, 'In Review' => true, 'Done' => true],
            'In Review' => ['To Do' => true, 'In Progress' => true, 'In Review' => true, 'Done' => true],
            'Done' => ['To Do' => true, 'In Progress' => true, 'In Review' => false, 'Done' => true],
        ];
    }

    public function test_既定のワークフローが4つのステータスを作る(): void
    {
        $this->assertSame(
            ['To Do', 'In Progress', 'In Review', 'Done'],
            $this->project->statuses()->pluck('name')->all(),
        );

        $this->assertSame(StatusCategory::Todo, $this->named('To Do')->category);
        $this->assertSame(StatusCategory::InProgress, $this->named('In Progress')->category);
        $this->assertSame(StatusCategory::InProgress, $this->named('In Review')->category);
        $this->assertSame(StatusCategory::Done, $this->named('Done')->category);
    }

    public function test_初期ステータスと完了ステータスが決まる(): void
    {
        $this->assertSame($this->named('To Do')->id, $this->project->initialStatus()->id);
        $this->assertSame($this->named('Done')->id, $this->project->doneStatus()->id);
    }

    /**
     * 遷移マトリクスの全 16 通りを allows() で確認する。
     */
    public function test_遷移マトリクスを網羅する(): void
    {
        foreach (self::matrix() as $from => $destinations) {
            foreach ($destinations as $to => $expected) {
                $issue = $this->issueAt($from);

                $this->assertSame(
                    $expected,
                    $this->workflows->allows($issue, $this->named($to)),
                    "「{$from}」→「{$to}」の判定が想定と違います",
                );
            }
        }
    }

    /**
     * 許可された遷移は実際に反映され、禁止された遷移は例外になって状態が変わらない。
     */
    public function test_遷移マトリクスどおりに実行される(): void
    {
        foreach (self::matrix() as $from => $destinations) {
            foreach ($destinations as $to => $expected) {
                $issue = $this->issueAt($from);
                $target = $this->named($to);

                if ($expected) {
                    $this->workflows->transition($issue, $target);

                    $this->assertSame(
                        $target->id,
                        $issue->fresh()->status_id,
                        "「{$from}」→「{$to}」が反映されていません",
                    );

                    continue;
                }

                try {
                    $this->workflows->transition($issue, $target);
                    $this->fail("「{$from}」→「{$to}」は禁止されているのに通ってしまいました");
                } catch (IllegalTransitionException $e) {
                    // 失敗しても状態は動かない
                    $this->assertSame($this->named($from)->id, $issue->fresh()->status_id);
                }
            }
        }
    }

    public function test_禁止された遷移は理由の分かる例外になる(): void
    {
        $issue = $this->issueAt('To Do');

        try {
            $this->workflows->transition($issue, $this->named('In Review'));
            $this->fail('例外が投げられませんでした');
        } catch (IllegalTransitionException $e) {
            $this->assertStringContainsString('To Do', $e->getMessage());
            $this->assertStringContainsString('In Review', $e->getMessage());
            $this->assertSame('To Do', $e->from->name);
            $this->assertSame('In Review', $e->to->name);
        }
    }

    public function test_同じステータスへの移動は遷移ではないので常に通る(): void
    {
        foreach ($this->statuses as $name => $status) {
            $issue = $this->issueAt($name);

            $this->assertTrue($this->workflows->allows($issue, $status));
            $this->assertSame($status->id, $this->workflows->transition($issue, $status)->status_id);
        }
    }

    // --- global transition（from が null）------------------------------------

    public function test_どこからでもToDoへ戻せる(): void
    {
        // from_status_id が null の 1 本だけで、3 方向の差し戻しを賄っている
        $global = $this->project->transitions()->whereNull('from_status_id')->sole();

        $this->assertSame($this->named('To Do')->id, $global->to_status_id);
        $this->assertTrue($global->isGlobal());

        foreach (['In Progress', 'In Review', 'Done'] as $from) {
            $this->assertTrue($this->workflows->allows($this->issueAt($from), $this->named('To Do')));
        }
    }

    public function test_global遷移を消すと差し戻せなくなる(): void
    {
        $this->project->transitions()->whereNull('from_status_id')->delete();

        // Done → To Do はこの 1 本に頼っていたので、消すと通らなくなる
        $this->assertFalse($this->workflows->allows($this->issueAt('Done'), $this->named('To Do')));
        // 個別に定義されている遷移は残る
        $this->assertTrue($this->workflows->allows($this->issueAt('To Do'), $this->named('In Progress')));
    }

    // --- 行き先の一覧 ---------------------------------------------------------

    public function test_いま行ける先だけが返る(): void
    {
        // To Do から In Review へは行けない。現在地の To Do も含まない
        $this->assertSame(
            ['In Progress', 'Done'],
            $this->workflows->availableFor($this->issueAt('To Do'))->pluck('name')->all(),
        );

        // Done から In Review へも戻れない
        $this->assertSame(
            ['To Do', 'In Progress'],
            $this->workflows->availableFor($this->issueAt('Done'))->pluck('name')->all(),
        );

        // In Progress からはどこへでも行ける
        $this->assertSame(
            ['To Do', 'In Review', 'Done'],
            $this->workflows->availableFor($this->issueAt('In Progress'))->pluck('name')->all(),
        );
    }

    public function test_行き先の一覧に現在地は含まれない(): void
    {
        foreach ($this->statuses as $name => $status) {
            $this->assertNotContains(
                $status->id,
                $this->workflows->availableFor($this->issueAt($name))->pluck('id')->all(),
            );
        }
    }

    // --- 例外的な状況 ---------------------------------------------------------

    public function test_遷移が未定義のプロジェクトは全許可になる(): void
    {
        // ワークフロー導入前に作られたプロジェクトを動かなくしないための逃げ道
        $this->project->transitions()->delete();

        foreach (self::matrix() as $from => $destinations) {
            foreach (array_keys($destinations) as $to) {
                $this->assertTrue(
                    $this->workflows->allows($this->issueAt($from), $this->named($to)),
                    "遷移未定義なのに「{$from}」→「{$to}」が塞がれています",
                );
            }
        }
    }

    public function test_別プロジェクトのステータスへは移せない(): void
    {
        $other = Project::factory()->create();
        $otherTodo = $other->statuses()->where('name', 'In Progress')->sole();

        $issue = $this->issueAt('To Do');

        // 同名・同カテゴリでも、別プロジェクトのステータスなら拒否する
        $this->assertFalse($this->workflows->allows($issue, $otherTodo));
    }

    public function test_ワークフローの導入は冪等(): void
    {
        $this->workflows->installDefaults($this->project);
        $this->workflows->installDefaults($this->project);

        $this->assertSame(4, $this->project->statuses()->count());
        $this->assertSame(8, $this->project->transitions()->count());
    }

    public function test_ステータス名を変えても遷移は保たれる(): void
    {
        // 遷移は ID で結ばれているので、名前の変更に影響されない
        $this->named('Done')->update(['name' => 'リリース済み']);

        $issue = $this->issueAt('In Progress');
        $released = $this->project->statuses()->where('name', 'リリース済み')->sole();

        $this->assertTrue($this->workflows->allows($issue, $released));
        $this->assertTrue($this->workflows->transition($issue, $released)->isCompleted());
    }

    // --- 完了時刻 -------------------------------------------------------------

    public function test_完了カテゴリに入ると完了時刻が入る(): void
    {
        $issue = $this->issueAt('In Progress');
        $issue->forceFill(['completed_at' => null])->save();

        $this->workflows->transition($issue, $this->named('Done'));

        $this->assertNotNull($issue->fresh()->completed_at);
    }

    public function test_完了カテゴリから出ると完了時刻が消える(): void
    {
        $issue = $this->issueAt('Done');

        $this->workflows->transition($issue, $this->named('In Progress'));

        $this->assertNull($issue->fresh()->completed_at);
    }

    public function test_完了のまま別の完了ステータスへ移っても打刻は変わらない(): void
    {
        $issue = $this->issueAt('Done');
        $first = $issue->completed_at;

        // In Review は進行中カテゴリなので、いったん Done → In Progress → Done
        $this->workflows->transition($issue, $this->named('In Progress'));
        $this->workflows->transition($issue, $this->named('Done'));

        // 一度カテゴリを出ているので打刻し直される
        $this->assertNotNull($issue->fresh()->completed_at);
        $this->assertTrue($issue->fresh()->completed_at->greaterThanOrEqualTo($first));
    }

    // --- 完了トグル -----------------------------------------------------------

    public function test_完了トグルは完了ステータスと初期ステータスを往復する(): void
    {
        $issue = $this->issueAt('To Do');

        $this->workflows->toggleCompletion($issue);
        $this->assertSame($this->named('Done')->id, $issue->fresh()->status_id);

        $this->workflows->toggleCompletion($issue);
        $this->assertSame($this->named('To Do')->id, $issue->fresh()->status_id);
    }

    public function test_完了トグルもワークフローの検査を受ける(): void
    {
        // To Do → Done を塞ぐと、1 クリック完了も通らなくなる
        $this->project->transitions()
            ->where('from_status_id', $this->named('To Do')->id)
            ->where('to_status_id', $this->named('Done')->id)
            ->delete();

        $this->expectException(IllegalTransitionException::class);

        $this->workflows->toggleCompletion($this->issueAt('To Do'));
    }
}
