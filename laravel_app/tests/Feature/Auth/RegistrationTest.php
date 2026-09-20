<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_登録画面が表示される(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('アカウントを作成');
    }

    public function test_登録するとログイン状態でタスク一覧に遷移する(): void
    {
        $response = $this->post(route('register'), [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('tasks.index'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'taro@example.com']);
    }

    public function test_パスワードはハッシュ化して保存される(): void
    {
        $this->post(route('register'), [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertNotSame('password123', User::first()->password);
    }

    public function test_登録済みのメールアドレスは弾かれる(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);

        $this->post(route('register'), [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_確認用パスワードが一致しないと登録できない(): void
    {
        $this->post(route('register'), [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_短すぎるパスワードは登録できない(): void
    {
        $this->post(route('register'), [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'pass1',
            'password_confirmation' => 'pass1',
        ])->assertSessionHasErrors('password');
    }
}
