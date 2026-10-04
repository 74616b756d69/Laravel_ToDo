<?php

namespace Tests\Feature\Webhook;

use App\Enums\ProjectRole;
use App\Enums\WebhookEvent;
use App\Enums\WebhookFormat;
use App\Jobs\DeliverWebhook;
use App\Models\Project;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Support\Webhooks\WebhookUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Webhook の設定画面（管理者のみ）と、送り先 URL の検査。
 */
class WebhookSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(WebhookUrlGuard::class, new WebhookUrlGuard(fn (string $host) => match ($host) {
            'internal.example.com' => ['10.0.0.5'],
            'nowhere.example.com' => [],
            default => ['93.184.216.34'],
        }));

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->project = Project::personalFor($this->owner);
        $this->project->members()->create(['user_id' => $this->member->id, 'role' => ProjectRole::Member]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => '#dev',
            'url' => 'https://hooks.example.com/abc',
            'format' => 'slack',
            'events' => ['issue.created', 'comment.created'],
            'is_active' => '1',
            ...$overrides,
        ];
    }

    public function test_管理者は_Webhook_を追加でき鍵は暗号化して保存される(): void
    {
        $this->actingAs($this->owner)
            ->post(route('projects.webhooks.store', $this->project), $this->payload())
            ->assertRedirect();

        $webhook = Webhook::sole();
        $this->assertSame(WebhookFormat::Slack, $webhook->format);
        $this->assertSame(['issue.created', 'comment.created'], $webhook->events);
        $this->assertStringStartsWith('whsec_', $webhook->secret);

        $raw = DB::table('webhooks')->value('secret');
        $this->assertNotSame($webhook->secret, $raw);
        $this->assertSame($webhook->secret, Crypt::decryptString($raw));
    }

    public function test_メンバーは設定を見ることも追加することもできない(): void
    {
        $this->actingAs($this->member)->get(route('projects.webhooks.index', $this->project))->assertForbidden();
        $this->actingAs($this->member)
            ->post(route('projects.webhooks.store', $this->project), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('webhooks', 0);
    }

    public function test_http_の送り先は受けない(): void
    {
        $this->actingAs($this->owner)
            ->post(route('projects.webhooks.store', $this->project), $this->payload(['url' => 'http://hooks.example.com/abc']))
            ->assertSessionHasErrors(['url' => '送り先は https の URL にしてください。']);
    }

    public function test_内部ネットワークへの送り先は受けない(): void
    {
        foreach ([
            'https://internal.example.com/hook',
            'https://127.0.0.1/hook',
            'https://169.254.169.254/latest/meta-data',
            'https://[::1]/hook',
        ] as $url) {
            $this->actingAs($this->owner)
                ->post(route('projects.webhooks.store', $this->project), $this->payload(['url' => $url]))
                ->assertSessionHasErrors(['url' => '内部ネットワークのアドレスには送れません。']);
        }

        $this->assertDatabaseCount('webhooks', 0);
    }

    public function test_設定で許せば手元の受け口にも送れる(): void
    {
        config(['webhooks.allow_http' => true, 'webhooks.allow_private_hosts' => true]);

        $this->actingAs($this->owner)
            ->post(route('projects.webhooks.store', $this->project), $this->payload(['url' => 'http://127.0.0.1:9000/hook']))
            ->assertSessionHasNoErrors();
    }

    public function test_名前が引けない送り先と認証情報入りの_URL_は受けない(): void
    {
        $this->actingAs($this->owner)
            ->post(route('projects.webhooks.store', $this->project), $this->payload(['url' => 'https://nowhere.example.com/x']))
            ->assertSessionHasErrors('url');

        $this->actingAs($this->owner)
            ->post(route('projects.webhooks.store', $this->project), $this->payload(['url' => 'https://user:pass@hooks.example.com/x']))
            ->assertSessionHasErrors('url');
    }

    public function test_送る出来事は_1_つ以上で_ping_は選べない(): void
    {
        $this->actingAs($this->owner)
            ->post(route('projects.webhooks.store', $this->project), $this->payload(['events' => []]))
            ->assertSessionHasErrors('events');

        $this->actingAs($this->owner)
            ->post(route('projects.webhooks.store', $this->project), $this->payload(['events' => ['ping']]))
            ->assertSessionHasErrors('events.0');
    }

    public function test_更新と削除(): void
    {
        $webhook = Webhook::factory()->for($this->project)->create();

        $this->actingAs($this->owner)
            ->put(route('projects.webhooks.update', [$this->project, $webhook]), $this->payload(['name' => '改名', 'is_active' => '0']))
            ->assertRedirect();

        $webhook->refresh();
        $this->assertSame('改名', $webhook->name);
        $this->assertFalse($webhook->is_active);

        $this->actingAs($this->owner)
            ->delete(route('projects.webhooks.destroy', [$this->project, $webhook]))
            ->assertRedirect(route('projects.webhooks.index', $this->project));
        $this->assertModelMissing($webhook);
    }

    public function test_他のプロジェクトの_Webhook_は_URL_を差し替えても触れない(): void
    {
        $other = Webhook::factory()->create();

        $this->actingAs($this->owner)
            ->get(route('projects.webhooks.show', [$this->project, $other]))
            ->assertNotFound();
    }

    public function test_鍵を作り直せる(): void
    {
        $webhook = Webhook::factory()->for($this->project)->create();
        $before = $webhook->secret;

        $this->actingAs($this->owner)->post(route('projects.webhooks.secret', [$this->project, $webhook]));

        $this->assertNotSame($before, $webhook->refresh()->secret);
    }

    public function test_テスト送信は無効な_Webhook_でも送れる(): void
    {
        Queue::fake();
        $webhook = Webhook::factory()->for($this->project)->inactive()->create();

        $this->actingAs($this->owner)->post(route('projects.webhooks.ping', [$this->project, $webhook]))->assertRedirect();

        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->delivery->event === WebhookEvent::Ping);
    }

    public function test_再送は同じ中身で新しい配信を作る(): void
    {
        Queue::fake();
        $webhook = Webhook::factory()->for($this->project)->create();
        $original = WebhookDelivery::factory()->for($webhook)->create([
            'event' => WebhookEvent::IssueCreated,
            'payload' => ['event' => 'issue.created', 'n' => 1],
        ]);

        $this->actingAs($this->owner)
            ->post(route('projects.webhooks.redeliver', [$this->project, $webhook, $original]))
            ->assertRedirect();

        $this->assertDatabaseCount('webhook_deliveries', 2);
        $copy = WebhookDelivery::latest('id')->first();
        $this->assertSame(['event' => 'issue.created', 'n' => 1], $copy->payload);
    }

    public function test_設定画面の一覧では_URL_の鍵の部分を伏せる(): void
    {
        Webhook::factory()->for($this->project)->slack()->create();

        $this->actingAs($this->owner)->get(route('projects.webhooks.index', $this->project))
            ->assertOk()
            ->assertSee('https://hooks.slack.com/••••')
            ->assertDontSee('T000/B000');
    }

    public function test_詳細画面に配信履歴が出る(): void
    {
        $webhook = Webhook::factory()->for($this->project)->create();
        WebhookDelivery::factory()->for($webhook)->create(['error' => '相手のサーバーが 500 を返しました。']);

        $this->actingAs($this->owner)->get(route('projects.webhooks.show', [$this->project, $webhook]))
            ->assertOk()
            ->assertSee('配信履歴')
            ->assertSee('相手のサーバーが 500 を返しました。');
    }
}
