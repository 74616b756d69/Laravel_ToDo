<?php

namespace Tests\Feature\Webhook;

use App\Enums\DeliveryStatus;
use App\Enums\ProjectRole;
use App\Enums\StatusCategory;
use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Support\Webhooks\WebhookUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 課題のイベントから Webhook が送られるまで。
 *
 * 誰に（どの Webhook に）送るか・何を送るか・失敗したらどうするか・
 * 送ってはいけない先に送らないか、を確かめる。
 */
class WebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Project $project;

    private Issue $issue;

    protected function setUp(): void
    {
        parent::setUp();

        // DNS を引かずに、外部のアドレスへ解決したことにする
        $this->app->instance(WebhookUrlGuard::class, new WebhookUrlGuard(fn () => ['93.184.216.34']));

        $this->owner = User::factory()->create(['name' => '管理者', 'email' => 'owner@example.com']);
        $this->member = User::factory()->create(['name' => 'メンバー']);
        $this->project = Project::personalFor($this->owner);
        $this->project->members()->create(['user_id' => $this->member->id, 'role' => ProjectRole::Member]);

        $this->issue = Issue::factory()
            ->inProject($this->project, $this->owner)
            ->inCategory(StatusCategory::Todo)
            ->create(['title' => 'ログインできない']);
    }

    private function named(string $name)
    {
        return $this->project->statuses()->where('name', $name)->sole();
    }

    public function test_ステータスを変えると購読している_Webhook_に署名付きで届く(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('ok', 200)]);
        $webhook = Webhook::factory()->for($this->project)->create();

        $this->actingAs($this->owner)
            ->patch(route('tasks.transition', $this->issue), ['status' => $this->named('進行中')->id]);

        Http::assertSent(function (Request $request) use ($webhook) {
            $timestamp = $request->header('X-Tracklet-Timestamp')[0];
            $expected = 'sha256='.hash_hmac('sha256', "{$timestamp}.{$request->body()}", $webhook->secret);

            return $request->url() === 'https://hooks.example.com/tracklet'
                && $request->header('X-Tracklet-Event')[0] === 'issue.transitioned'
                && hash_equals($expected, $request->header('X-Tracklet-Signature')[0])
                && $request['issue']['key'] === $this->issue->key()
                && $request['changes']['status'] === ['from' => '未着手', 'to' => '進行中']
                && $request['actor'] === ['id' => $this->owner->id, 'name' => '管理者'];
        });

        $delivery = WebhookDelivery::sole();
        $this->assertSame(DeliveryStatus::Succeeded, $delivery->status);
        $this->assertSame(200, $delivery->response_status);
        $this->assertNotNull($delivery->delivered_at);
    }

    public function test_本文にメールアドレスを含めない(): void
    {
        Http::fake();
        Webhook::factory()->for($this->project)->create();

        $this->actingAs($this->owner)
            ->patch(route('tasks.assignee', $this->issue), ['assignee' => $this->member->id]);

        Http::assertSent(fn (Request $request) => ! str_contains($request->body(), '@example.com'));
    }

    public function test_購読していない出来事と無効な_Webhook_には送らない(): void
    {
        Queue::fake();
        Webhook::factory()->for($this->project)->listening([WebhookEvent::CommentCreated])->create();
        Webhook::factory()->for($this->project)->inactive()->create();

        $this->actingAs($this->owner)
            ->patch(route('tasks.transition', $this->issue), ['status' => $this->named('進行中')->id]);

        Queue::assertNotPushed(DeliverWebhook::class);
        $this->assertDatabaseCount('webhook_deliveries', 0);
    }

    public function test_他のプロジェクトの_Webhook_には送らない(): void
    {
        Queue::fake();
        Webhook::factory()->create();

        $this->actingAs($this->owner)
            ->patch(route('tasks.transition', $this->issue), ['status' => $this->named('進行中')->id]);

        Queue::assertNotPushed(DeliverWebhook::class);
    }

    public function test_送信はキューに積みリクエストの中では待たない(): void
    {
        Queue::fake();
        Webhook::factory()->for($this->project)->create();

        $this->actingAs($this->owner)->post(route('comments.store', $this->issue), ['body' => '<p>見ました</p>']);

        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->delivery->event === WebhookEvent::CommentCreated
            && $job->delivery->payload['comment']['excerpt'] === '見ました');
    }

    public function test_操作した人がいない変更は送らない(): void
    {
        Queue::fake();
        Webhook::factory()->for($this->project)->create();

        Issue::factory()->inProject($this->project, $this->owner)->create();

        Queue::assertNotPushed(DeliverWebhook::class);
    }

    public function test_Slack_の書式では課題へのリンク付きの文を送り制御文字を逃がす(): void
    {
        Http::fake();
        Webhook::factory()->for($this->project)->slack()->create();
        $this->issue->update(['title' => '<!channel> 緊急']);

        $this->actingAs($this->owner)
            ->patch(route('tasks.transition', $this->issue), ['status' => $this->named('進行中')->id]);

        Http::assertSent(function (Request $request) {
            $text = $request['text'];

            return str_contains($text, '*管理者* さんが <'.route('tasks.legacy', $this->issue->id).'|')
                && str_contains($text, '&lt;!channel&gt; 緊急')
                && ! str_contains($text, '<!channel>')
                && str_contains($text, '「未着手」→「進行中」');
        });
    }

    public function test_5xx_なら記録を残して送り直す(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);
        $delivery = WebhookDelivery::factory()->for(Webhook::factory()->for($this->project))->create();

        $job = (new DeliverWebhook($delivery))->withFakeQueueInteractions();
        $job->handle(app(WebhookUrlGuard::class));

        $job->assertReleased(delay: 10);
        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Pending, $delivery->status);
        $this->assertSame(503, $delivery->response_status);
        $this->assertSame(1, $delivery->attempts);
    }

    public function test_4xx_は送り直さずに失敗にする(): void
    {
        Http::fake(['*' => Http::response('gone', 410)]);
        $delivery = WebhookDelivery::factory()->for(Webhook::factory()->for($this->project))->create();

        $job = (new DeliverWebhook($delivery))->withFakeQueueInteractions();
        $job->handle(app(WebhookUrlGuard::class));

        $job->assertNotReleased();
        $this->assertSame(DeliveryStatus::Failed, $delivery->refresh()->status);
    }

    public function test_429_は一時的な失敗として送り直す(): void
    {
        Http::fake(['*' => Http::response('slow down', 429)]);
        $delivery = WebhookDelivery::factory()->for(Webhook::factory()->for($this->project))->create();

        $job = (new DeliverWebhook($delivery))->withFakeQueueInteractions();
        $job->handle(app(WebhookUrlGuard::class));

        $job->assertReleased();
    }

    public function test_接続できなければ送り直す(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'));
        $delivery = WebhookDelivery::factory()->for(Webhook::factory()->for($this->project))->create();

        $job = (new DeliverWebhook($delivery))->withFakeQueueInteractions();
        $job->handle(app(WebhookUrlGuard::class));

        $job->assertReleased();
        $this->assertStringContainsString('Connection refused', $delivery->refresh()->error);
    }

    public function test_送る直前に内部のアドレスへ向いていたら送らない(): void
    {
        Http::fake();
        // 登録時は外部だった名前が、あとから内部へ付け替えられた（DNS リバインディング）
        $this->app->instance(WebhookUrlGuard::class, new WebhookUrlGuard(fn () => ['169.254.169.254']));
        $delivery = WebhookDelivery::factory()->for(Webhook::factory()->for($this->project))->create();

        $job = (new DeliverWebhook($delivery))->withFakeQueueInteractions();
        $job->handle(app(WebhookUrlGuard::class));

        Http::assertNothingSent();
        $job->assertNotReleased();
        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Failed, $delivery->status);
        $this->assertSame('内部ネットワークのアドレスには送れません。', $delivery->error);
    }

    public function test_古い配信記録は掃除される(): void
    {
        $webhook = Webhook::factory()->for($this->project)->create();
        $old = WebhookDelivery::factory()->for($webhook)->create(['created_at' => now()->subDays(31)]);
        $recent = WebhookDelivery::factory()->for($webhook)->create(['created_at' => now()->subDays(29)]);

        $this->artisan('model:prune', ['--model' => [WebhookDelivery::class]]);

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }
}
