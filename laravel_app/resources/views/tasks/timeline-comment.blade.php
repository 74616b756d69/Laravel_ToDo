@php
    /*
     * コメントの検証エラーは、このコメント専用の袋に入っている（CommentRequest）。
     * 1 つの画面にコメントが何個も並ぶので、既定の袋に入れると
     * 「どのコメントの話なのか」が分からなくなり、投稿フォームにまで出てしまう。
     */
    $bag = "comment-{$comment->id}";

    /*
     * 打ち直しの内容（old）を拾うのは、失敗したのがこのコメントだったときだけ。
     * old は画面で 1 つしかないので、無条件に読むと
     * 隣のコメントの書きかけが自分の中身として出てしまう。
     */
    $body = $errors->getBag($bag)->has('body') ? old('body', $comment->body) : $comment->body;
@endphp

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

        {{-- 残るのは削除だけ。書き直しは本文をダブルクリックすればその場で始まる --}}
        @can('delete', $comment)
            <form action="{{ route('comments.destroy', [$task, $comment]) }}" method="POST" class="ml-auto"
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

    <x-inline-edit :editable="auth()->user()->can('update', $comment)"
                   :action="route('comments.update', [$task, $comment])" method="PUT"
                   field="body" :bag="$bag" label="コメント"
                   trigger-class="-mx-2 items-start px-2 py-1">
        <x-slot:display>
            {{-- 保存時に許可タグだけへサニタイズ済みなので、そのまま描画する --}}
            <div class="prose-content min-w-0 flex-1">{!! \App\Support\RichText::forDisplay($comment->body) !!}</div>
        </x-slot:display>

        <x-rich-editor name="body" :value="$body" placeholder="コメントを書く" />
        <x-input-error :messages="$errors->getBag($bag)->get('body')" />
    </x-inline-edit>
</li>
