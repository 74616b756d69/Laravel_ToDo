@extends('layouts.app')

@section('title', $webhook->name.' | Webhook')

@section('content')
    <x-page-heading :title="$webhook->name" :back="route('projects.webhooks.index', $project)" back-label="Webhook 一覧" />

    <div class="-mt-4 mb-6 flex flex-wrap items-center gap-2">
        <form action="{{ route('projects.webhooks.ping', [$project, $webhook]) }}" method="POST">
            @csrf
            <button type="submit" class="btn-primary px-4 py-2">
                <x-icon name="sparkles" class="size-4" /> テスト送信
            </button>
        </form>

        <form action="{{ route('projects.webhooks.destroy', [$project, $webhook]) }}" method="POST" class="ml-auto"
              data-confirm="Webhook「{{ $webhook->name }}」を削除します。配信履歴も消えます。よろしいですか？">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-rose-300 px-4 py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-50 dark:border-rose-500/40 dark:text-rose-300 dark:hover:bg-rose-500/10">
                <x-icon name="trash" class="size-4" /> 削除
            </button>
        </form>
    </div>

    @include('projects.webhooks.form', ['action' => route('projects.webhooks.update', [$project, $webhook])])

    {{-- 署名の鍵。JSON 書式の受け手が、届いた通知が本物かを確かめるのに使う --}}
    @if ($webhook->format === \App\Enums\WebhookFormat::Generic)
        <section class="mt-8">
            <h2 class="mb-1 text-lg font-bold tracking-tight">署名の検証</h2>
            <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">
                各リクエストには <code>X-Tracklet-Timestamp</code> と <code>X-Tracklet-Signature</code> が付きます。
                受け手は <code>HMAC-SHA256(鍵, "タイムスタンプ.本文")</code> を計算して <code>sha256=…</code> と比べ、
                タイムスタンプが古すぎるもの（5 分以上前など）は捨ててください。
            </p>

            <div class="card flex flex-wrap items-center gap-3 p-4">
                <details class="min-w-0 flex-1">
                    <summary class="cursor-pointer text-sm font-medium">鍵を表示</summary>
                    <code class="mt-2 block break-all rounded-lg bg-slate-100 px-3 py-2 font-mono text-xs dark:bg-white/5">{{ $webhook->secret }}</code>
                </details>

                <form action="{{ route('projects.webhooks.secret', [$project, $webhook]) }}" method="POST"
                      data-confirm="鍵を作り直すと、受け手の設定を変えるまで検証に失敗します。よろしいですか？">
                    @csrf
                    <button type="submit" class="btn-quiet px-3 py-2">作り直す</button>
                </form>
            </div>
        </section>
    @endif

    <section class="mt-8">
        <h2 class="mb-1 text-lg font-bold tracking-tight">配信履歴</h2>
        <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">
            直近 {{ \App\Models\WebhookDelivery::RETENTION_DAYS }} 日ぶんを残します。行を開くと、送った中身と相手の応答が見られます。
        </p>

        <div class="card overflow-hidden">
            @forelse ($deliveries as $delivery)
                <details class="group border-b border-slate-100 last:border-b-0 dark:border-white/5">
                    <summary class="flex cursor-pointer flex-wrap items-center gap-3 px-4 py-3 text-sm hover:bg-slate-50 dark:hover:bg-white/5">
                        <x-badge :classes="$delivery->status->badgeClasses()">{{ $delivery->status->label() }}</x-badge>
                        <span class="font-medium">{{ $delivery->event->label() }}</span>
                        <code class="text-xs text-slate-400">{{ $delivery->event->value }}</code>
                        @if ($delivery->response_status)
                            <span class="font-mono text-xs text-slate-500 dark:text-slate-400">HTTP {{ $delivery->response_status }}</span>
                        @endif
                        <span class="ml-auto flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                            {{ $delivery->attempts }} 回
                            <time datetime="{{ $delivery->created_at->toIso8601String() }}">{{ $delivery->created_at->isoFormat('M/D HH:mm:ss') }}</time>
                        </span>
                    </summary>

                    <div class="space-y-3 bg-slate-50/60 px-4 py-3 text-xs dark:bg-white/[0.02]">
                        @if ($delivery->error)
                            <p class="text-rose-600 dark:text-rose-400">{{ $delivery->error }}</p>
                        @endif

                        <div>
                            <p class="mb-1 font-medium text-slate-500 dark:text-slate-400">送った中身</p>
                            <pre class="max-h-64 overflow-auto rounded-lg bg-slate-900 p-3 text-slate-100">{{ json_encode($delivery->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>

                        @if ($delivery->response_body !== null)
                            <div>
                                <p class="mb-1 font-medium text-slate-500 dark:text-slate-400">相手の応答</p>
                                <pre class="max-h-40 overflow-auto rounded-lg bg-slate-900 p-3 text-slate-100">{{ $delivery->response_body }}</pre>
                            </div>
                        @endif

                        <form action="{{ route('projects.webhooks.redeliver', [$project, $webhook, $delivery]) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn-quiet px-3 py-1.5">同じ中身で再送</button>
                        </form>
                    </div>
                </details>
            @empty
                <p class="px-4 py-6 text-center text-sm text-slate-400 dark:text-slate-500">まだ配信していません。</p>
            @endforelse
        </div>

        <div class="mt-4">{{ $deliveries->links() }}</div>
    </section>
@endsection
