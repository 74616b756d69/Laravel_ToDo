<?php

namespace Tests\Feature\Issue;

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 表示用キー（PROJ-123）は保存せず、project.key + issue_number から導出する。
 */
class IssueKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_キーはプロジェクトキーと課題番号から組み立てられる(): void
    {
        $project = Project::factory()->create(['key' => 'PROJ']);
        $issue = Issue::factory()->inProject($project)->create();

        $this->assertSame("PROJ-{$issue->issue_number}", $issue->key());
    }

    public function test_キーは保存されない(): void
    {
        // 組み立て式にしている理由は「プロジェクトキーを変えても全行 UPDATE が要らない」こと。
        // キーらしきカラムが増えていないことを構造として固定しておく
        $columns = Schema::getColumnListing('tasks');

        $this->assertNotContains('issue_key', $columns);
        $this->assertNotContains('key', $columns);
    }

    public function test_プロジェクトキーを変えるとキーの表示も変わる(): void
    {
        $project = Project::factory()->create(['key' => 'OLD']);
        $issue = Issue::factory()->inProject($project)->create();

        $this->assertSame("OLD-{$issue->issue_number}", $issue->key());

        $project->update(['key' => 'NEW']);

        // 課題側は 1 行も更新していないのに表示が追従する
        $this->assertSame("NEW-{$issue->issue_number}", $issue->fresh()->key());
    }

    public function test_課題番号はプロジェクトごとに1から始まる(): void
    {
        $user = User::factory()->create();
        $a = Project::factory()->create(['key' => 'AAA']);
        $b = Project::factory()->create(['key' => 'BBB']);

        $first = $a->createIssue(['title' => 'A の 1 件目', 'reporter_id' => $user->id]);
        $second = $b->createIssue(['title' => 'B の 1 件目', 'reporter_id' => $user->id]);

        $this->assertSame('AAA-1', $first->key());
        $this->assertSame('BBB-1', $second->key());
    }

    public function test_番号は削除しても詰め直されない(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->withMember($user)->create(['key' => 'PROJ']);

        $project->createIssue(['title' => '1件目', 'reporter_id' => $user->id]);
        $second = $project->createIssue(['title' => '2件目', 'reporter_id' => $user->id]);
        $second->forceDelete();
        $third = $project->createIssue(['title' => '3件目', 'reporter_id' => $user->id]);

        // 欠番は許容する。番号を使い回すと、消えた課題への言及が別物を指してしまう
        $this->assertSame('PROJ-3', $third->key());
    }

    public function test_一覧と詳細にキーが表示される(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->withMember($user)->create(['key' => 'DEMO']);
        $issue = Issue::factory()->inProject($project, $user)->create();

        $this->actingAs($user)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertSee($issue->key());

        $this->actingAs($user)
            ->get(route('tasks.show', $issue))
            ->assertOk()
            ->assertSee($issue->key());
    }
}
