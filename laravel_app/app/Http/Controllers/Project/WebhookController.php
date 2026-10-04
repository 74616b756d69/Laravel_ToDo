<?php

namespace App\Http\Controllers\Project;

use App\Enums\WebhookEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\WebhookRequest;
use App\Models\Project;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\WebhookDispatcher;
use App\Support\Webhooks\WebhookPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * プロジェクトの Webhook 設定（管理者のみ）。
 *
 * URL の {webhook} / {delivery} は scopeBindings で {project} 配下のものしか解決しない。
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookDispatcher $dispatcher,
    ) {}

    public function index(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.webhooks.index', [
            'project' => $project,
            'webhooks' => $project->webhooks()
                ->with(['deliveries' => fn ($query) => $query->limit(1)])
                ->get(),
        ]);
    }

    public function store(WebhookRequest $request, Project $project): RedirectResponse
    {
        $webhook = $project->webhooks()->create($request->webhookAttributes());

        return redirect()->route('projects.webhooks.show', [$project, $webhook])
            ->with('status', "Webhook「{$webhook->name}」を追加しました。テスト送信で疎通を確かめられます。");
    }

    public function show(Project $project, Webhook $webhook): View
    {
        $this->authorize('update', $project);

        return view('projects.webhooks.show', [
            'project' => $project,
            'webhook' => $webhook,
            'deliveries' => $webhook->deliveries()->paginate(20),
        ]);
    }

    public function update(WebhookRequest $request, Project $project, Webhook $webhook): RedirectResponse
    {
        $webhook->update($request->webhookAttributes());

        return redirect()->route('projects.webhooks.show', [$project, $webhook])
            ->with('status', "Webhook「{$webhook->name}」を更新しました。");
    }

    public function destroy(Project $project, Webhook $webhook): RedirectResponse
    {
        $this->authorize('update', $project);

        $webhook->delete();

        return redirect()->route('projects.webhooks.index', $project)
            ->with('status', "Webhook「{$webhook->name}」を削除しました。");
    }

    /**
     * 署名の鍵を作り直す。漏れたかもしれないときの手当て。
     * 受け手の設定も変える必要があるので、それまでの通知は検証に失敗する。
     */
    public function rotateSecret(Project $project, Webhook $webhook): RedirectResponse
    {
        $this->authorize('update', $project);

        $webhook->forceFill(['secret' => Webhook::generateSecret()])->save();

        return redirect()->route('projects.webhooks.show', [$project, $webhook])
            ->with('status', '署名の鍵を作り直しました。受け手の設定も新しい鍵に変えてください。');
    }

    /**
     * テスト送信。無効にしてある Webhook でも送れる（有効にする前に確かめたいので）。
     */
    public function ping(Request $request, Project $project, Webhook $webhook): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->dispatcher->send($webhook, WebhookEvent::Ping, WebhookPayload::ping($project, $request->user()));

        return redirect()->route('projects.webhooks.show', [$project, $webhook])
            ->with('status', 'テスト送信をキューに積みました。結果は下の配信履歴に出ます。');
    }

    /**
     * 再送。そのときの中身をそのまま、新しい配信として送る（元の記録は残す）。
     */
    public function redeliver(Project $project, Webhook $webhook, WebhookDelivery $delivery): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->dispatcher->send($webhook, $delivery->event, $delivery->payload);

        return redirect()->route('projects.webhooks.show', [$project, $webhook])
            ->with('status', '再送をキューに積みました。');
    }
}
