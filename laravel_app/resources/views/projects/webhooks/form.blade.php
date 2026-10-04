{{--
    Webhook の追加・編集フォーム。
    $webhook が null なら追加。
--}}
@php
    $selectedEvents = old('events', $webhook?->events ?? array_map(fn ($event) => $event->value, \App\Enums\WebhookEvent::subscribable()));
    $format = old('format', $webhook?->format->value ?? \App\Enums\WebhookFormat::Slack->value);
@endphp

<form action="{{ $action }}" method="POST" class="card space-y-4 p-5 sm:p-6">
    @csrf
    @if ($webhook)
        @method('PUT')
    @endif

    <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
        <div>
            <label for="webhook-name" class="field-label">名前</label>
            <input id="webhook-name" name="name" type="text" required maxlength="60"
                   value="{{ old('name', $webhook?->name) }}" placeholder="#dev チャンネル" class="field">
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div>
            <label for="webhook-format" class="field-label">書式</label>
            <select id="webhook-format" name="format" class="field">
                @foreach (\App\Enums\WebhookFormat::options() as $value => $label)
                    <option value="{{ $value }}" @selected($format === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('format')" />
        </div>
    </div>

    <div>
        <label for="webhook-url" class="field-label">送り先 URL</label>
        {{-- 一覧ではホストより後ろを伏せるが、直すための画面なのでここ（管理者のみ）では全体を出す --}}
        <input id="webhook-url" name="url" type="url" required maxlength="2048"
               value="{{ old('url', $webhook?->url) }}"
               placeholder="https://hooks.slack.com/services/…" class="field font-mono text-xs" autocomplete="off">
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            https のみ。社内ネットワークのアドレスには送れません。
        </p>
        <x-input-error :messages="$errors->get('url')" />
    </div>

    <fieldset>
        <legend class="field-label">送る出来事</legend>
        <div class="flex flex-wrap gap-x-5 gap-y-2">
            @foreach (\App\Enums\WebhookEvent::subscribable() as $event)
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="events[]" value="{{ $event->value }}"
                           @checked(in_array($event->value, $selectedEvents, true))
                           class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
                    {{ $event->label() }}
                    <code class="text-xs text-slate-400">{{ $event->value }}</code>
                </label>
            @endforeach
        </div>
        <x-input-error :messages="$errors->get('events')" />
    </fieldset>

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-white/5">
        <label class="inline-flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $webhook?->is_active ?? true))
                   class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
            有効にする
        </label>

        <button type="submit" class="btn-primary px-5 py-2.5">
            <x-icon :name="$webhook ? 'check' : 'plus'" class="size-4" /> {{ $webhook ? '保存' : '追加' }}
        </button>
    </div>
</form>
