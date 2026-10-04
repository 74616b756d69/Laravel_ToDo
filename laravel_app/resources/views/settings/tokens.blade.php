@extends('layouts.app')

@section('title', 'API トークン')

@section('content')
    <x-page-heading title="API トークン" />

    <p class="-mt-4 mb-6 text-sm text-slate-500 dark:text-slate-400">
        外部のツールやスクリプトから Tracklet の REST API（<code>/api/v1</code>）を使うための鍵です。
        <code>Authorization: Bearer &lt;トークン&gt;</code> を付けて呼び出します。トークンはあなたと同じ権限で動くので、人に渡さないでください。
    </p>

    {{-- 発行した直後だけ見せる。再読み込みすると消える --}}
    @if ($plainToken)
        <div role="alert" class="card mb-6 space-y-2 border-amber-300 bg-amber-50 p-4 dark:border-amber-500/40 dark:bg-amber-500/10">
            <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                このトークンは今しか表示されません。いまコピーして、安全な場所に保管してください。
            </p>
            <code class="block break-all rounded-lg bg-white px-3 py-2 font-mono text-xs dark:bg-black/30">{{ $plainToken }}</code>
            <p class="text-xs text-amber-700 dark:text-amber-300">
                試すには: <code>curl -H "Authorization: Bearer …" {{ url('/api/v1/me') }}</code>
            </p>
        </div>
    @endif

    <form action="{{ route('settings.tokens.store') }}" method="POST" class="card space-y-4 p-5 sm:p-6">
        @csrf
        <div class="grid gap-4 sm:grid-cols-[1fr_10rem]">
            <div>
                <label for="token-name" class="field-label">名前</label>
                <input id="token-name" name="name" type="text" required maxlength="60" value="{{ old('name') }}"
                       placeholder="CI から課題を作る" class="field">
                <x-input-error :messages="$errors->get('name')" />
            </div>
            <div>
                <label for="token-expires" class="field-label">有効期限</label>
                <select id="token-expires" name="expires" class="field">
                    @foreach (\App\Http\Controllers\Settings\ApiTokenController::EXPIRATIONS as $value => $label)
                        <option value="{{ $value }}" @selected(old('expires', '90') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <fieldset>
            <legend class="field-label">権限</legend>
            <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="abilities[]" value="read" @checked(in_array('read', old('abilities', ['read']), true))
                           class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
                    読み取り <code class="text-xs text-slate-400">read</code>
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="abilities[]" value="write" @checked(in_array('write', old('abilities', []), true))
                           class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
                    書き込み <code class="text-xs text-slate-400">write</code>（課題の作成・更新・削除、コメント）
                </label>
            </div>
            <x-input-error :messages="$errors->get('abilities')" />
        </fieldset>

        <div class="flex justify-end border-t border-slate-100 pt-4 dark:border-white/5">
            <button type="submit" class="btn-primary px-5 py-2.5"><x-icon name="plus" class="size-4" /> 発行</button>
        </div>
    </form>

    @if ($tokens->isNotEmpty())
        <section class="mt-8">
            <h2 class="mb-3 text-lg font-bold tracking-tight">発行済みのトークン</h2>
            <div class="card overflow-hidden">
                <ul class="divide-y divide-slate-100 dark:divide-white/5">
                    @foreach ($tokens as $token)
                        <li class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ $token->name }}
                                    @foreach ($token->abilities as $ability)
                                        <code class="ml-1 text-xs text-slate-400">{{ $ability }}</code>
                                    @endforeach
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    最後に使った日時: {{ $token->last_used_at?->isoFormat('YYYY/M/D HH:mm') ?? '未使用' }}
                                    ・ 期限: {{ $token->expires_at?->isoFormat('YYYY/M/D') ?? '無期限' }}
                                    @if ($token->expires_at?->isPast())
                                        <span class="font-medium text-rose-600 dark:text-rose-400">（期限切れ）</span>
                                    @endif
                                </p>
                            </div>
                            <form action="{{ route('settings.tokens.destroy', $token->id) }}" method="POST"
                                  data-confirm="トークン「{{ $token->name }}」を取り消します。これを使っている連携は動かなくなります。よろしいですか？">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-rose-600 hover:underline dark:text-rose-400">取り消す</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
