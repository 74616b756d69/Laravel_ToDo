{{--
    コメントと履歴の時系列。$timeline は kind / at / item の配列。
    履歴は不変なので、操作ボタンはコメントにしか付かない。
--}}
@php
    $tabs = [
        'all' => 'すべて',
        'comments' => 'コメント',
        'history' => '履歴',
    ];
@endphp

<section class="mt-8">
    <div class="mb-4 flex items-center gap-1 border-b border-slate-200 dark:border-slate-800">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('tasks.show', ['task' => $task, 'tab' => $key]) }}"
               @class([
                   '-mb-px border-b-2 px-4 py-2 text-sm transition',
                   'border-brand-600 font-medium text-slate-900 dark:border-brand-400 dark:text-white' => $tab === $key,
                   'border-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' => $tab !== $key,
               ])>
                {{ $label }}
                @if ($key === 'comments')
                    <span class="ml-1 tabular-nums text-slate-400">{{ $task->comments->count() }}</span>
                @elseif ($key === 'history')
                    <span class="ml-1 tabular-nums text-slate-400">{{ $task->activities->count() }}</span>
                @endif
            </a>
        @endforeach
    </div>

    {{-- コメント投稿フォーム。履歴タブでは出さない --}}
    @if ($tab !== 'history')
        @can('create', [\App\Models\Comment::class, $task])
            <form action="{{ route('comments.store', $task) }}" method="POST" class="card mb-4 space-y-3 p-4">
                @csrf
                <x-rich-editor name="body" :value="old('body')" placeholder="コメントを書く" />
                <x-input-error :messages="$errors->get('body')" />

                <div class="flex justify-end">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">
                        <x-icon name="check" class="size-4" /> コメントする
                    </button>
                </div>
            </form>
        @endcan
    @endif

    @if ($timeline->isEmpty())
        <p class="card px-6 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
            @if ($tab === 'comments')
                まだコメントはありません。
            @elseif ($tab === 'history')
                まだ変更履歴はありません。
            @else
                まだコメントも履歴もありません。
            @endif
        </p>
    @else
        <ol class="space-y-2">
            @foreach ($timeline as $entry)
                @if ($entry['kind'] === 'comment')
                    @include('tasks.timeline-comment', ['comment' => $entry['item']])
                @else
                    @include('tasks.timeline-activity', ['activity' => $entry['item']])
                @endif
            @endforeach
        </ol>
    @endif
</section>
