<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Tag;
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
        $task = Issue::factory()->forUser($user)->create(['title' => 'サンプルタスク']);

        $this->actingAs($user);

        $tag = Tag::factory()->for($user)->create(['name' => 'サンプルタグ']);
        $task->tags()->attach($tag);
        Issue::factory()->childOf($task)->create(['title' => 'サンプルサブタスク']);

        $this->get(route('tasks.index'))->assertOk()->assertSee('サンプルタグ');
        $this->get(route('tasks.create'))->assertOk()->assertSee('タスクを作成');
        $this->get(route('tasks.show', $task))->assertOk()
            ->assertSee('サンプルタスク')
            ->assertSee('サンプルサブタスク');
        $this->get(route('tasks.edit', $task))->assertOk()->assertSee('タスクを編集');
        $this->get(route('board'))->assertOk()->assertSee('ボード');
        $this->get(route('dashboard'))->assertOk()->assertSee('分析');
        $this->get(route('tags.index'))->assertOk()->assertSee('サンプルタグ');
    }

    public function test_データが一件も無くても各画面が表示される(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('tasks.index'))->assertOk();
        $this->get(route('board'))->assertOk();
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('tags.index'))->assertOk();
    }

    public function test_存在しないタスクは404になる(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('tasks.show', 999))
            ->assertNotFound();
    }
}
