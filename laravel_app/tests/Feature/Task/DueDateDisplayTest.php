<?php

namespace Tests\Feature\Task;

use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesWorkflow;
use Tests\TestCase;

/**
 * 一覧での期限・優先度・見積りの見せ方。
 *
 * 近い期限と超過だけを相対表示にし、読む必要のない記号は出さない、
 * という方針が崩れていないかを固定しておく。
 */
class DueDateDisplayTest extends TestCase
{
    use RefreshDatabase, UsesWorkflow;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function issueDue(?\DateTimeInterface $dueDate, array $attributes = []): Issue
    {
        return Issue::factory()->forUser($this->user)->create([
            'due_date' => $dueDate,
            'status_id' => $this->statusIdFor($this->user, '未着手'),
            ...$attributes,
        ]);
    }

    public function test_期限切れは超過日数で示す(): void
    {
        $this->issueDue(today()->subDays(13));

        $this->actingAs($this->user)->get(route('tasks.index'))
            ->assertSee('13日超過');
    }

    public function test_近い期限は今日_明日_あと何日で示す(): void
    {
        $this->issueDue(today());
        $this->issueDue(today()->addDay());
        $this->issueDue(today()->addDays(2));

        $this->actingAs($this->user)->get(route('tasks.index'))
            ->assertSee('今日')
            ->assertSee('明日')
            ->assertSee('あと2日');
    }

    public function test_先の期限は日付のまま示しホバーで正確な日付が分かる(): void
    {
        $due = today()->addDays(20);
        $this->issueDue($due);

        $this->actingAs($this->user)->get(route('tasks.index'))
            ->assertSee($due->year === today()->year ? $due->format('n/j') : $due->format('Y/n/j'))
            ->assertSee('期限 '.$due->format('Y/n/j'))
            ->assertDontSee('あと20日');
    }

    public function test_完了済みは相対表示にしない(): void
    {
        $this->issueDue(today()->subDays(3), ['status_id' => $this->statusIdFor($this->user, '完了')]);

        $this->actingAs($this->user)->get(route('tasks.index'))
            ->assertDontSee('3日超過');
    }

    public function test_詳細画面では日付と相対表示を併記する(): void
    {
        $due = today()->subDays(4);
        $issue = $this->issueDue($due);

        $this->actingAs($this->user)->get(route('tasks.show', $issue))
            ->assertSee($due->year === today()->year ? $due->format('n/j') : $due->format('Y/n/j'))
            ->assertSee('4日超過');
    }

    public function test_一覧では高以外の優先度記号を出さない(): void
    {
        $this->issueDue(null, ['priority' => TaskPriority::High]);
        $this->issueDue(null, ['priority' => TaskPriority::Low]);

        $this->actingAs($this->user)->get(route('tasks.index'))
            ->assertSee('優先度高')
            ->assertDontSee('aria-label="優先度低"', false);
    }

    public function test_詳細画面では低い優先度も出す(): void
    {
        $issue = $this->issueDue(null, ['priority' => TaskPriority::Low]);

        $this->actingAs($this->user)->get(route('tasks.show', $issue))
            ->assertSee('aria-label="優先度低"', false);
    }

    public function test_一覧では見積り0を出さない(): void
    {
        $this->issueDue(null, ['story_points' => 0]);

        $this->actingAs($this->user)->get(route('tasks.index'))
            ->assertDontSee('title="ストーリーポイント"', false);
    }
}
