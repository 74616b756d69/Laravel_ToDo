<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ログイン画面が表示される(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_正しい認証情報でログインできる(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('tasks.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_誤ったパスワードではログインできない(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_ログイン時にセッションIDが再生成される(): void
    {
        $user = User::factory()->create();

        $this->startSession();
        $before = session()->getId();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_6回目の失敗でログイン試行が制限される(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $ignored) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        RateLimiter::clear(mb_strtolower($user->email).'|127.0.0.1');
    }

    public function test_ログアウトできる(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_未ログインではタスク一覧を開けない(): void
    {
        $this->get(route('tasks.index'))->assertRedirect(route('login'));
    }
}
