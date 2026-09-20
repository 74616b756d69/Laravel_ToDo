<?php

namespace Tests\Feature\Issue;

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 課題番号の採番が並行実行でも重複しないことを実証する。
 *
 * RefreshDatabase を使わないのは、テスト既定の :memory: が接続ごとに
 * 別のデータベースになり、そもそも並行の再現ができないため。
 * ここだけファイル実体の SQLite を作って、本物のプロセスを並べる。
 *
 * ただし SQLite では lockForUpdate（FOR UPDATE）が黙って無視される。
 * SQLite で証明できるのは「重複が起きないこと」までで、
 * 行ロックそのものが効いていることを確かめられるのは MySQL のケースだけ。
 */
class IssueNumberConcurrencyTest extends TestCase
{
    /** 同時に走らせるプロセス数 */
    private const WORKERS = 10;

    private string $databasePath;

    private string $resultDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = tempnam(sys_get_temp_dir(), 'issue-seq-').'.sqlite';
        touch($this->databasePath);

        $this->resultDir = $this->databasePath.'-results';
        mkdir($this->resultDir);

        $this->useFileDatabase();

        Artisan::call('migrate', ['--database' => 'concurrency', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('concurrency');

        array_map('unlink', glob($this->resultDir.'/*') ?: []);
        @rmdir($this->resultDir);
        @unlink($this->databasePath);

        parent::tearDown();
    }

    /**
     * 接続ごとに別 DB になる :memory: ではなく、共有できるファイルを使う。
     * busy_timeout は、SQLite の書き込みロック衝突で即座に諦めないために要る。
     */
    private function useFileDatabase(): void
    {
        Config::set('database.connections.concurrency', [
            'driver' => 'sqlite',
            'database' => $this->databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 10_000,
        ]);

        Config::set('database.default', 'concurrency');
        DB::purge('concurrency');
    }

    private function makeProject(string $key = 'PROJ'): Project
    {
        $user = User::factory()->create();

        $organization = $user->ownedOrganizations()->create([
            'name' => 'テスト組織', 'slug' => 'test-'.$user->id,
        ]);

        return $organization->projects()->create(['key' => $key, 'name' => 'テスト']);
    }

    public function test_フォークした10プロセスが同時に採番しても重複しない(): void
    {
        if (! extension_loaded('pcntl')) {
            $this->markTestSkipped('pcntl が無いので、本物の並行実行を再現できません。');
        }

        $project = $this->makeProject();

        $this->runInParallel(self::WORKERS, function (int $worker) use ($project) {
            $number = Project::findOrFail($project->id)->allocateIssueNumber();

            file_put_contents("{$this->resultDir}/{$worker}", (string) $number);
        });

        $numbers = $this->collectResults();

        $this->assertCount(self::WORKERS, $numbers, '採番に失敗したプロセスがあります');
        $this->assertSame(
            range(1, self::WORKERS),
            $numbers,
            '採番が重複または欠落しました: '.implode(',', $numbers),
        );
    }

    public function test_フォークした10プロセスが同時に課題を作っても重複しない(): void
    {
        if (! extension_loaded('pcntl')) {
            $this->markTestSkipped('pcntl が無いので、本物の並行実行を再現できません。');
        }

        $project = $this->makeProject();
        $user = User::first();

        $this->runInParallel(self::WORKERS, function (int $worker) use ($project, $user) {
            $issue = Project::findOrFail($project->id)->createIssue([
                'title' => "並行作成 {$worker}",
                'reporter_id' => $user->id,
            ]);

            file_put_contents("{$this->resultDir}/{$worker}", (string) $issue->issue_number);
        });

        $this->assertSame(range(1, self::WORKERS), $this->collectResults());

        // DB の実データも重複していないこと
        $stored = Issue::where('project_id', $project->id)
            ->orderBy('issue_number')
            ->pluck('issue_number')
            ->all();

        $this->assertSame(range(1, self::WORKERS), array_map('intval', $stored));
    }

    /**
     * unique(project_id, issue_number) が最後の砦として働くことの直接検証。
     *
     * SQLite では行ロックが効かないので、この制約が無ければ並行採番は破綻する。
     */
    public function test_unique制約が二重採番を弾く(): void
    {
        $project = $this->makeProject();
        $user = User::factory()->create();

        $issue = $project->createIssue(['title' => '1件目', 'reporter_id' => $user->id]);

        // 例外の種類まで指定する。QueryException だけだと、
        // 列名を間違えただけでも通ってしまい、制約を検証したことにならない
        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('tasks')->insert([
            'project_id' => $project->id,
            // わざと同じ番号を差し込む
            'issue_number' => $issue->issue_number,
            'issue_type' => 'task',
            'status_id' => $project->initialStatus()->id,
            'reporter_id' => $user->id,
            'title' => '重複した番号',
            'priority' => 'medium',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 採番のカウンタ読みに行ロックが乗っていること。
     *
     * 「重複しない」だけを見ていると、ロックを外しても unique 制約と
     * リトライが結果を救ってしまい、テストが通ってしまう。
     * 機構そのものをここで固定する（性能の担保はこれしかない）。
     *
     * SQLite の文法では FOR UPDATE が出力されないので、対応するドライバでのみ確認する。
     */
    public function test_採番のカウンタ読みに行ロックが乗る(): void
    {
        // このテストクラスは既定で SQLite を使うが、SQLite の文法では
        // FOR UPDATE が出力されない。MySQL に切り替えてから確かめる
        $this->skipUnlessMysqlIsReachable();

        Config::set('database.default', 'mysql_concurrency');
        DB::purge('mysql_concurrency');
        Artisan::call('migrate:fresh', ['--database' => 'mysql_concurrency', '--force' => true]);

        $project = $this->makeProject();

        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = strtolower($query->sql);
        });

        $project->allocateIssueNumber();

        $reads = array_values(array_filter(
            $sql,
            fn (string $q) => str_contains($q, 'select') && str_contains($q, 'last_issue_number'),
        ));

        $this->assertNotEmpty($reads, 'カウンタを読むクエリが見当たりません');
        $this->assertStringContainsString(
            'for update',
            $reads[0],
            '採番のカウンタ読みに行ロックが乗っていません',
        );
    }

    public function test_採番はプロジェクトごとに独立している(): void
    {
        $a = $this->makeProject('AAA');
        $b = $this->makeProject('BBB');
        $user = User::first();

        // 交互に作っても、それぞれが 1 から独立して進む
        foreach (range(1, 3) as $n) {
            $this->assertSame($n, $a->createIssue(['title' => "A{$n}", 'reporter_id' => $user->id])->issue_number);
            $this->assertSame($n, $b->createIssue(['title' => "B{$n}", 'reporter_id' => $user->id])->issue_number);
        }
    }

    /**
     * MySQL で同じ検証を行う。**FOR UPDATE が実際に効くのはこのケースだけ。**
     * 接続できない環境ではスキップする。
     */
    public function test_mysqlでも並行採番が重複しない(): void
    {
        if (! extension_loaded('pcntl')) {
            $this->markTestSkipped('pcntl が無いので、本物の並行実行を再現できません。');
        }

        $this->skipUnlessMysqlIsReachable();

        Config::set('database.default', 'mysql_concurrency');
        DB::purge('mysql_concurrency');

        Artisan::call('migrate:fresh', ['--database' => 'mysql_concurrency', '--force' => true]);

        $project = $this->makeProject();
        $user = User::first();

        $this->runInParallel(self::WORKERS, function (int $worker) use ($project, $user) {
            $issue = Project::findOrFail($project->id)->createIssue([
                'title' => "並行作成 {$worker}",
                'reporter_id' => $user->id,
            ]);

            file_put_contents("{$this->resultDir}/{$worker}", (string) $issue->issue_number);
        });

        $this->assertSame(range(1, self::WORKERS), $this->collectResults());
    }

    private function skipUnlessMysqlIsReachable(): void
    {
        Config::set('database.connections.mysql_concurrency', [
            ...Config::get('database.connections.mysql'),
            'database' => env('DB_TEST_DATABASE', 'testing'),
        ]);

        try {
            DB::connection('mysql_concurrency')->getPdo();
        } catch (\Throwable $e) {
            DB::purge('mysql_concurrency');

            $this->markTestSkipped('MySQL に接続できないためスキップします: '.$e->getMessage());
        }
    }

    /**
     * 子プロセスを N 本立てて同時に実行する。
     *
     * fork すると親の PDO ハンドルが共有されてしまうので、
     * 子では必ず接続を捨ててから張り直す。ここを忘れると
     * 「並行しているつもりで同じ接続を使い回す」テストになってしまう。
     */
    private function runInParallel(int $workers, callable $work): void
    {
        $pids = [];

        for ($worker = 1; $worker <= $workers; $worker++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('プロセスを fork できませんでした。');
            }

            if ($pid === 0) {
                // --- 子プロセス ---
                $exit = 0;

                try {
                    DB::purge();
                    $work($worker);
                } catch (\Throwable $e) {
                    file_put_contents("{$this->resultDir}/{$worker}.error", $e->getMessage());
                    $exit = 1;
                }

                // テストランナーの終了処理を走らせないため exit() で即座に抜ける
                exit($exit);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $errors = glob($this->resultDir.'/*.error') ?: [];

        if ($errors !== []) {
            $this->fail('子プロセスが失敗しました: '.implode(' / ', array_map('file_get_contents', $errors)));
        }
    }

    /**
     * 子プロセスが書き出した採番結果を昇順で集める。
     *
     * @return array<int, int>
     */
    private function collectResults(): array
    {
        $numbers = collect(glob($this->resultDir.'/*') ?: [])
            ->map(fn (string $file) => (int) file_get_contents($file))
            ->sort()
            ->values()
            ->all();

        return $numbers;
    }
}
