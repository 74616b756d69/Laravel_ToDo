<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoLoginTest extends TestCase
{
    use RefreshDatabase;

    private function createDemoUser(): User
    {
        return User::factory()->create(['email' => config('demo.email')]);
    }

    public function test_ログイン画面にデモの案内が出る(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('デモアカウントでログイン')
            ->assertSee(config('demo.email'));
    }

    public function test_ワンクリックでデモアカウントにログインできる(): void
    {
        $demo = $this->createDemoUser();

        $this->post(route('login.demo'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($demo);
    }

    public function test_デモログイン時もセッション_idが再生成される(): void
    {
        $this->createDemoUser();

        $this->startSession();
        $before = session()->getId();

        $this->post(route('login.demo'));

        $this->assertNotSame($before, session()->getId());
    }

    public function test_デモアカウントが未作成なら案内を返す(): void
    {
        $this->from(route('login'))
            ->post(route('login.demo'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_設定で無効化すると入口ごと消える(): void
    {
        config(['demo.enabled' => false]);
        $this->createDemoUser();

        $this->post(route('login.demo'))->assertNotFound();
        $this->get(route('login'))->assertDontSee('デモアカウントでログイン');
        $this->assertGuest();
    }

    public function test_ログイン中にデモログインしてもアカウントは切り替わらない(): void
    {
        $this->createDemoUser();
        $current = User::factory()->create();

        // guest ミドルウェア配下なので、処理そのものが実行されない
        $this->actingAs($current)->post(route('login.demo'))->assertRedirect();

        $this->assertAuthenticatedAs($current);
    }
}
