<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ショートカットは JS で動くので、ここでは「JS が探す目印が画面にあるか」を確かめる。
 * 目印の名前を変えたら resources/js/features/shortcuts.js も合わせること。
 */
class KeyboardShortcutsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ログイン中の画面には一覧のダイアログと行き先の目印がある(): void
    {
        $this->actingAs(User::factory()->create())->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('data-shortcuts-dialog', false)
            ->assertSee('キーボードショートカット')
            ->assertSee('id="global-search"', false);

        foreach (['issues', 'board', 'backlog', 'dashboard', 'notifications', 'create'] as $target) {
            $this->get(route('tasks.index'))->assertSee("data-shortcut-target=\"{$target}\"", false);
        }
    }

    public function test_課題の画面にはウォッチとコメントの目印がある(): void
    {
        $user = User::factory()->create();
        $issue = Issue::factory()->forUser($user)->create();

        $this->actingAs($user)->get(route('tasks.show', ['task' => $issue, 'tab' => 'comments']))
            ->assertOk()
            ->assertSee('data-shortcut-watch', false)
            ->assertSee('data-comment-form', false);
    }

    public function test_ログインしていない画面には出さない(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('data-shortcuts-dialog', false);
    }
}
