<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 各画面が例外なく描画できることを確認する。
 */
class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_トップページが表示される(): void
    {
        $this->get(route('welcome'))->assertOk()->assertSee('Laravel 製タスク管理アプリ');
    }

    public function test_ログイン後の各画面が表示される(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['title' => 'サンプルタスク']);

        $this->actingAs($user);

        $this->get(route('tasks.index'))->assertOk();
        $this->get(route('tasks.create'))->assertOk()->assertSee('タスクを作成');
        $this->get(route('tasks.show', $task))->assertOk()->assertSee('サンプルタスク');
        $this->get(route('tasks.edit', $task))->assertOk()->assertSee('タスクを編集');
    }

    public function test_存在しないタスクは404になる(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('tasks.show', 999))
            ->assertNotFound();
    }
}
