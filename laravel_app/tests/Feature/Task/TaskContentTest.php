<?php

namespace Tests\Feature\Task;

use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesWorkflow;
use Tests\TestCase;

class TaskContentTest extends TestCase
{
    use RefreshDatabase, UsesWorkflow;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_危険なhtmlは保存時に除去される(): void
    {
        $this->actingAs($this->user)->post(route('tasks.store'), [
            'title' => 'リッチテキスト',
            'content' => '<p>安全な本文</p><script>alert(1)</script>',
            'status' => $this->statusIdFor($this->user, 'To Do'),
            'priority' => TaskPriority::Low->value,
        ]);

        $task = Issue::sole();

        $this->assertStringContainsString('安全な本文', $task->content);
        $this->assertStringNotContainsString('script', $task->content);
    }

    public function test_保存時に検索用の平文カラムが同期される(): void
    {
        $this->actingAs($this->user)->post(route('tasks.store'), [
            'title' => 'リッチテキスト',
            'content' => '<h2>見出し</h2><p>本文です</p>',
            'status' => $this->statusIdFor($this->user, 'To Do'),
            'priority' => TaskPriority::Low->value,
        ]);

        $this->assertSame('見出し 本文です', Issue::sole()->content_text);
    }

    public function test_検索はhtmlタグにヒットしない(): void
    {
        Issue::factory()->forUser($this->user)->create([
            'title' => 'タグに引っかからないこと',
            'content' => '<p>本文</p>',
        ]);

        $this->actingAs($this->user)
            ->get(route('tasks.index', ['keyword' => 'p']))
            ->assertDontSee('タグに引っかからないこと');
    }
}
