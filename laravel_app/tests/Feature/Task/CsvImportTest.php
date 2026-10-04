<?php

namespace Tests\Feature\Task;

use App\Enums\ImportStatus;
use App\Enums\IssueType;
use App\Enums\ProjectRole;
use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\IssueImport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * CSV からの取り込み。キューは sync なので、POST の中で取り込みまで終わる。
 */
class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->owner = User::factory()->create(['email' => 'owner@example.com']);
        $this->member = User::factory()->create(['email' => 'member@example.com']);
        $this->project = Project::personalFor($this->owner);
        $this->project->members()->create(['user_id' => $this->member->id, 'role' => ProjectRole::Member]);
    }

    private function csv(string $contents, string $name = 'issues.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    private function upload(string $contents, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->owner)->post(route('tasks.import.store'), ['file' => $this->csv($contents)]);
    }

    public function test_CSV_から課題を作る(): void
    {
        $csv = "\xEF\xBB\xBFタイトル,説明,タイプ,ステータス,優先度,担当者メール,ストーリーポイント,見積もり（分）,期限,タグ\n"
            ."ログイン修正,\"1行目\n2行目\",バグ,進行中,高,member@example.com,3,90,2026-10-31,\"UI,緊急\"\n"
            ."資料作成,,,,,,,2h,,\n";

        $response = $this->upload($csv);

        $import = IssueImport::sole();
        $response->assertRedirect(route('tasks.import.show', $import));
        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(2, $import->imported_rows);

        $bug = Issue::where('title', 'ログイン修正')->sole();
        $this->assertSame(IssueType::Bug, $bug->issue_type);
        $this->assertSame('進行中', $bug->status->name);
        $this->assertSame(TaskPriority::High, $bug->priority);
        $this->assertSame($this->member->id, $bug->assignee_id);
        $this->assertSame($this->owner->id, $bug->reporter_id);
        $this->assertSame(3, $bug->story_points);
        $this->assertSame(90, $bug->original_estimate_minutes);
        $this->assertSame('2026-10-31', $bug->due_date->toDateString());
        $this->assertSame(['UI', '緊急'], $bug->tags->pluck('name')->sort()->values()->all());
        $this->assertMatchesRegularExpression('#<p>1行目<br\s*/?>\s*2行目</p>#', $bug->content);

        $plain = Issue::where('title', '資料作成')->sole();
        $this->assertSame(IssueType::Task, $plain->issue_type);
        $this->assertSame(120, $plain->original_estimate_minutes);
        $this->assertNull($plain->assignee_id);

        // 元のファイルは残さない
        Storage::disk('local')->assertMissing($import->path);
    }

    public function test_1_行でも誤りがあれば_1_件も作らない(): void
    {
        $csv = "タイトル,優先度,担当者メール,期限\n"
            ."正しい行,中,,\n"
            .",中,,\n"
            ."三行目,最高,stranger@example.com,2026-02-31\n";

        $this->upload($csv);

        $import = IssueImport::sole();
        $this->assertSame(ImportStatus::Failed, $import->status);
        $this->assertDatabaseCount('tasks', 0);

        $messages = collect($import->errors)->map(fn ($error) => "{$error['row']}: {$error['message']}")->all();
        $this->assertContains('3: タイトルが空です。', $messages);
        $this->assertContains('4: 優先度「最高」はありません（高 / 中 / 低）。', $messages);
        $this->assertContains('4: 担当者「stranger@example.com」はこのプロジェクトのメンバーではありません。', $messages);
        $this->assertContains('4: 期限「2026-02-31」が読めません（2026-10-31 の形）。', $messages);

        $this->actingAs($this->owner)->get(route('tasks.import.show', $import))
            ->assertOk()
            ->assertSee('1 件も取り込んでいません')
            ->assertSee('タイトルが空です。');
    }

    public function test_Excel_の_Shift_JIS_も読める(): void
    {
        $this->upload(mb_convert_encoding("タイトル\n請求書を送る\n", 'SJIS-win', 'UTF-8'));

        $this->assertSame('請求書を送る', Issue::sole()->title);
    }

    public function test_エクスポートしたファイルをそのまま読み込める(): void
    {
        Issue::factory()->inProject($this->project, $this->owner)->create(['title' => '往復する課題', 'priority' => TaskPriority::Low]);
        $exported = $this->actingAs($this->owner)->get(route('tasks.export'))->streamedContent();
        Issue::query()->forceDelete();

        $this->upload($exported);

        $this->assertSame(ImportStatus::Completed, IssueImport::sole()->status);
        $issue = Issue::sole();
        $this->assertSame('往復する課題', $issue->title);
        $this->assertSame(TaskPriority::Low, $issue->priority);
    }

    public function test_タイトルの列が無いファイルは受けない(): void
    {
        $this->upload("名前,期限\nなにか,2026-10-01\n");

        $this->assertSame('見出しの行に「タイトル」の列がありません。テンプレートを使ってください。', IssueImport::sole()->errors[0]['message']);
    }

    public function test_取り込みでは通知を飛ばさない(): void
    {
        Notification::fake();

        $this->upload("タイトル,担当者メール\n通知しない,member@example.com\n");

        $this->assertSame(1, Issue::count());
        Notification::assertNothingSent();
    }

    public function test_閲覧者は取り込めない(): void
    {
        $viewer = User::factory()->create();
        $this->project->members()->create(['user_id' => $viewer->id, 'role' => ProjectRole::Viewer]);
        $this->actingAs($viewer)->patch(route('projects.switch'), ['project' => $this->project->id]);

        $this->upload("タイトル\nx\n", $viewer)->assertForbidden();
        $this->assertDatabaseCount('issue_imports', 0);
    }

    public function test_他人の取り込み結果は見えない(): void
    {
        $this->upload("タイトル\nx\n");

        $this->actingAs($this->member)->get(route('tasks.import.show', IssueImport::sole()))->assertNotFound();
    }

    public function test_CSV_以外のファイルは受けない(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tasks.import.store'), ['file' => UploadedFile::fake()->image('a.png')])
            ->assertSessionHasErrors('file');
    }

    public function test_テンプレートを配る(): void
    {
        $body = $this->actingAs($this->owner)->get(route('tasks.import.template'))->assertOk()->streamedContent();

        $this->assertStringContainsString('タイトル,説明,タイプ', $body);
    }
}
