<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_トークンを発行すると一度だけ文字列を見せる(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('settings.tokens.store'), [
            'name' => 'CI',
            'abilities' => ['write'],
            'expires' => '30',
        ])->assertRedirect(route('settings.tokens'));

        $plain = session('plain_token');
        $this->assertNotEmpty($plain);

        $token = PersonalAccessToken::sole();
        // write には read を含める。期限は 30 日後
        $this->assertSame(['write', 'read'], $token->abilities);
        $this->assertTrue($token->expires_at->isSameDay(now()->addDays(30)));
        // DB にはハッシュだけ
        $this->assertNotSame($plain, $token->token);

        $this->get(route('settings.tokens'))->assertSee($plain);
        $this->get(route('settings.tokens'))->assertDontSee($plain);
    }

    public function test_他人のトークンは取り消せない(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $owner->createToken('mine', ['read']);
        $token = PersonalAccessToken::sole();

        $this->actingAs($intruder)->delete(route('settings.tokens.destroy', $token->id))->assertNotFound();
        $this->assertModelExists($token);

        $this->actingAs($owner)->delete(route('settings.tokens.destroy', $token->id))->assertRedirect();
        $this->assertModelMissing($token);
    }

    public function test_権限を選ばないと発行しない(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('settings.tokens.store'), ['name' => 'x', 'abilities' => [], 'expires' => '30'])
            ->assertSessionHasErrors('abilities');
    }
}
