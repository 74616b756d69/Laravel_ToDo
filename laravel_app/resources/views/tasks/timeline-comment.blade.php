<li class="card p-4">
    <div class="mb-2 flex flex-wrap items-baseline gap-x-2 gap-y-1">
        <span class="text-sm font-medium">{{ $comment->authorName() }}</span>
        <time datetime="{{ $comment->created_at->toIso8601String() }}"
              class="text-xs text-slate-500 dark:text-slate-400"
              title="{{ $comment->created_at->isoFormat('YYYY年M月D日(ddd) HH:mm') }}">
            {{ $comment->created_at->diffForHumans() }}
        </time>
        @if ($comment->wasEdited())
            <span class="text-xs text-slate-400 dark:text-slate-500"
                  title="{{ $comment->edited_at->isoFormat('YYYY年M月D日(ddd) HH:mm') }}">（編集済み）</span>
        @endif

        <div class="ml-auto flex items-center gap-1">
            @can('update', $comment)
                {{-- 編集は details で開く。JS 無しでも動く --}}
                <details class="relative">
                    <summary class="cursor-pointer list-none rounded p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-white/5 dark:hover:text-white">
                        <x-icon name="pencil" class="size-4" />
                        <span class="sr-only">コメントを編集</span>
                    </summary>

                    <form action="{{ route('comments.update', [$task, $comment]) }}" method="POST"
                          class="mt-3 space-y-3">
                        @csrf
                        @method('PUT')
                        <x-rich-editor :name="'body'" :value="$comment->body" />
                        <div class="flex justify-end">
                            <button type="submit"
                                    class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700">
                                保存
                            </button>
                        </div>
                    </form>
                </details>
            @endcan

            @can('delete', $comment)
                <form action="{{ route('comments.destroy', [$task, $comment]) }}" method="POST"
                      data-confirm="このコメントを削除します。よろしいですか？">
                    @csrf
                    @method('DELETE')
                    <button type="submit" aria-label="コメントを削除"
                            class="rounded p-1.5 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10">
                        <x-icon name="trash" class="size-4" />
                    </button>
                </form>
            @endcan
        </div>
    </div>

    {{-- 保存時に許可タグだけへサニタイズ済みなので、そのまま描画する --}}
    <div class="prose-content">{!! \App\Support\RichText::forDisplay($comment->body) !!}</div>
</li>
