<?php

namespace Tests\Feature\Task;

use App\Enums\StatusCategory;
use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use App\Models\Worklog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => '自分', 'email' => 'me@example.com']);
        $this->project = Project::personalFor($this->user);
    }

    /**
     * @return list<list<string>>
     */
    private function export(array $query = []): array
    {
        $response = $this->actingAs($this->user)->get(route('tasks.export', $query))->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body, 'Excel で文字化けしないよう BOM を付ける');

        return array_map(
            fn (string $line) => str_getcsv($line, escape: ''),
            array_values(array_filter(preg_split('/\r?\n/', substr($body, 3)))),
        );
    }

    public function test_見出しと課題の中身を書き出す(): void
    {
        $issue = Issue::factory()->inProject($this->project, $this->user)->inCategory(StatusCategory::InProgress)->create([
            'title' => 'ログイン不具合',
            'priority' => TaskPriority::High,
            'story_points' => 5,
            'original_estimate_minutes' => 120,
            'due_date' => '2026-10-31',
        ]);
        $issue->tags()->attach(Tag::factory()->for($this->user)->create(['name' => '緊急']));
        Worklog::factory()->for($issue)->create(['user_id' => $this->user->id, 'minutes' => 45]);

        $rows = $this->export();

        $this->assertSame('キー', $rows[0][0]);
        $row = array_combine($rows[0], $rows[1]);
        $this->assertSame($issue->key(), $row['キー']);
        $this->assertSame('ログイン不具合', $row['タイトル']);
        $this->assertSame('進行中', $row['ステータス']);
        $this->assertSame('高', $row['優先度']);
        $this->assertSame('me@example.com', $row['担当者メール']);
        $this->assertSame('5', $row['ストーリーポイント']);
        $this->assertSame('120', $row['見積もり（分）']);
        $this->assertSame('45', $row['実績（分）']);
        $this->assertSame('2026-10-31', $row['期限']);
        $this->assertSame('緊急', $row['タグ']);
    }

    public function test_一覧の絞り込み条件のまま書き出す(): void
    {
        Issue::factory()->inProject($this->project, $this->user)->create(['title' => '急ぎ', 'priority' => TaskPriority::High]);
        Issue::factory()->inProject($this->project, $this->user)->create(['title' => 'のんびり', 'priority' => TaskPriority::Low]);

        $titles = array_column(array_slice($this->export(['q' => 'priority:high']), 1), 1);

        $this->assertSame(['急ぎ'], $titles);
    }

    public function test_10_件を超えても全ページぶん書き出す(): void
    {
        Issue::factory()->count(25)->inProject($this->project, $this->user)->create();

        $this->assertCount(26, $this->export());
    }

    public function test_見えない課題は書き出さない(): void
    {
        Issue::factory()->forUser(User::factory()->create())->create(['title' => '他人の課題']);

        $this->assertCount(1, $this->export());
    }

    public function test_数式として動く値は逃がす(): void
    {
        Issue::factory()->inProject($this->project, $this->user)->create(['title' => '=HYPERLINK("https://evil.example")']);

        $row = array_combine(...array_slice($this->export(), 0, 2));

        $this->assertSame('\'=HYPERLINK("https://evil.example")', $row['タイトル']);
    }
}
