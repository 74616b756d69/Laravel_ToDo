{{-- 作成・設定で共有するフォーム本体。$action / $method / $project を受け取る --}}
<form action="{{ $action }}" method="POST" class="card space-y-5 p-5 sm:p-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="grid gap-5 sm:grid-cols-[10rem_1fr]">
        <div>
            <label for="key" class="field-label">課題キー <span class="text-rose-500">*</span></label>
            <input id="key" name="key" type="text" required maxlength="10"
                   value="{{ old('key', $project->key) }}" placeholder="PROJ"
                   class="field font-mono tracking-wider @error('key') border-rose-400 @enderror">
            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">大文字の英字 2〜10 文字</p>
            <x-input-error :messages="$errors->get('key')" />
        </div>

        <div>
            <label for="name" class="field-label">プロジェクト名 <span class="text-rose-500">*</span></label>
            <input id="name" name="name" type="text" required maxlength="60"
                   value="{{ old('name', $project->name) }}" placeholder="例：ポートフォリオ刷新"
                   class="field @error('name') border-rose-400 @enderror">
            <x-input-error :messages="$errors->get('name')" />
        </div>
    </div>

    <div>
        <label for="description" class="field-label">説明</label>
        <textarea id="description" name="description" rows="3" maxlength="500"
                  placeholder="このプロジェクトで何を進めるか" class="field">{{ old('description', $project->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" />
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-5 dark:border-white/5">
        <a href="{{ $cancelUrl }}"
           class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5">
            キャンセル
        </a>
        <button type="submit"
                class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">
            <x-icon name="check" class="size-4" /> {{ $submitLabel }}
        </button>
    </div>
</form>
