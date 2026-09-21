{{--
    リンクされた作業項目。
    サブタスク（親子）とは別で、関連づけても相手は一覧やボードに残る。
--}}
<section class="card px-5 py-5 sm:px-6">
    <div class="mb-3 flex items-center justify-between gap-3">
        <h2 class="text-sm font-semibold">リンクされた作業項目</h2>
        @if ($linkedIssues->isNotEmpty())
            <span class="text-xs text-slate-500 tabular-nums dark:text-slate-400">
                {{ $linkedIssues->flatten(1)->count() }} 件
            </span>
        @endif
    </div>

    {{-- 読み方ごとにまとめる。「が関連:」「をブロック:」… --}}
    @foreach ($linkedIssues as $label => $rows)
        <p class="mt-3 mb-1 text-xs text-slate-500 dark:text-slate-400">{{ $label }}:</p>
        <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200 dark:divide-white/5 dark:border-slate-700">
            @foreach ($rows as $row)
                <x-linked-issue-row :parent="$task" :link="$row['link']" :issue="$row['issue']" />
            @endforeach
        </ul>
    @endforeach

    @can('update', $task)
        <form action="{{ route('links.store', $task) }}" method="POST"
              class="mt-3 flex flex-col gap-2 sm:flex-row">
            @csrf
            <label class="sm:w-44">
                <span class="sr-only">関連の種類</span>
                <select name="type" class="field py-2 text-sm">
                    @foreach (\App\Enums\IssueLinkType::options() as $value => $label)
                        <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <div class="relative flex-1">
                <input type="text" name="target" maxlength="255" required
                       value="{{ old('target') }}"
                       placeholder="{{ $task->project->key }}-12 または詳細画面の URL"
                       class="field py-2 pr-10 text-sm">
                <button type="submit" aria-label="関連づける" title="関連づける（Enter）"
                        class="absolute inset-y-1 right-1 grid w-8 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white">
                    <x-icon name="enter" class="size-4" />
                </button>
            </div>
        </form>

        <x-input-error :messages="$errors->get('target')" />
        <x-input-error :messages="$errors->get('type')" />
    @elseif ($linkedIssues->isEmpty())
        <p class="text-sm text-slate-400 dark:text-slate-500">関連づけられた課題はありません。</p>
    @endcan
</section>
