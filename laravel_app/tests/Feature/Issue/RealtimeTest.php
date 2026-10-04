<?php

namespace Tests\Feature\Issue;

use App\Enums\ProjectRole;
use App\Enums\StatusCategory;
use App\Events\IssueChanged;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * リアルタイム更新（Reverb）。送る中身・送る条件・チャンネルの認可を確かめる。
 */
class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Project $project;

    private Issue $issue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => '管理者']);
        $this->member = User::factory()->create();
        $this->project = Project::personalFor($this->owner);
        $this->project->members()->create(['user_id' => $this->member->id, 'role' => ProjectRole::Member]);
        $this->issue = Issue::factory()->inProject($this->project, $this->owner)->inCategory(StatusCategory::Todo)->create();
    }

    public function test_ステータスを変えるとプロジェクトのチャンネルへキーと操作者だけを流す(): void
    {
        Event::fake([IssueChanged::class]);

        $this->actingAs($this->owner)->patch(route('tasks.transition', $this->issue), [
            'status' => $this->project->statuses()->where('name', '進行中')->sole()->id,
        ]);

        Event::assertDispatched(IssueChanged::class, function (IssueChanged $event) {
            $channel = $event->broadcastOn()[0];

            return $channel instanceof PrivateChannel
                && $channel->name === "private-projects.{$this->project->id}"
                && $event->broadcastAs() === 'issue.changed'
                && $event->broadcastWith() === [
                    'action' => 'updated',
                    'issue_id' => $this->issue->id,
                    'issue_key' => $this->issue->key(),
                    'actor_id' => $this->owner->id,
                    'actor_name' => '管理者',
                ];
        });
    }

    public function test_作成と削除も流す(): void
    {
        Event::fake([IssueChanged::class]);

        $this->actingAs($this->owner)->post(route('tasks.store'), [
            'title' => '新しい課題',
            'status' => $this->project->initialStatus()->id,
            'priority' => 'medium',
        ]);
        $this->actingAs($this->owner)->delete(route('tasks.destroy', $this->issue));

        Event::assertDispatched(IssueChanged::class, fn (IssueChanged $event) => $event->action === 'created');
        Event::assertDispatched(IssueChanged::class, fn (IssueChanged $event) => $event->action === 'deleted' && $event->issueId === $this->issue->id);
    }

    public function test_並べ替えだけの変更と操作者のいない変更は流さない(): void
    {
        Event::fake([IssueChanged::class]);

        $this->actingAs($this->owner);
        $this->issue->forceFill(['position' => 99])->save();
        auth()->logout();
        $this->issue->forceFill(['title' => 'シーダーからの変更'])->save();

        Event::assertNotDispatched(IssueChanged::class);
    }

    public function test_チャンネルはメンバーだけが購読できる(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb' => [
            'driver' => 'reverb', 'key' => 'k', 'secret' => 's', 'app_id' => '1',
            'options' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
        ]]);
        // チャンネルの定義は起動時の接続（log）に登録されている。切り替えた接続に登録し直す
        app(\Illuminate\Broadcasting\BroadcastManager::class)->purge();
        require base_path('routes/channels.php');

        $outsider = User::factory()->create();
        $payload = ['socket_id' => '1234.5678', 'channel_name' => "private-projects.{$this->project->id}"];

        $this->actingAs($this->member)->post('/broadcasting/auth', $payload)->assertOk()->assertJsonStructure(['auth']);
        $this->actingAs($outsider)->post('/broadcasting/auth', $payload)->assertForbidden();
    }

    public function test_Reverb_を使う設定のときだけ画面に接続先を出す(): void
    {
        $this->actingAs($this->owner)->get(route('board'))->assertOk()->assertDontSee('name="realtime"', false);

        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'app-key',
            'broadcasting.connections.reverb.client' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http']]);

        $this->actingAs($this->owner)->get(route('board'))
            ->assertOk()
            ->assertSee('name="realtime"', false)
            ->assertSee('app-key')
            ->assertSee("data-realtime-project=\"{$this->project->id}\"", false);
    }
}
