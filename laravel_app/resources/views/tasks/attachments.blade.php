{{--
    添付ファイル。

    画像は小さく見せ、それ以外は名前と大きさを 1 行で並べる。
    上げ方は 3 通り: ファイルを選ぶ / この欄へドロップ / 説明やコメントのエディタへ貼り付け。
    JS が無くても「選んで添付」のフォームだけで使える。
--}}
<section class="card px-5 py-5 sm:px-6" data-attachments
         @can('create', [\App\Models\Attachment::class, $task]) data-attachment-dropzone="{{ route('attachments.store', $task) }}" @endcan>
    <div class="mb-3 flex items-center justify-between gap-3">
        <h2 class="text-sm font-semibold">添付ファイル</h2>
        @if ($task->attachments->isNotEmpty())
            <span class="text-xs text-slate-500 tabular-nums dark:text-slate-400">{{ $task->attachments->count() }} 件</span>
        @endif
    </div>

    @if ($task->attachments->isNotEmpty())
        <ul class="mb-4 divide-y divide-slate-100 rounded-xl border border-slate-200 dark:divide-white/5 dark:border-slate-700">
            @foreach ($task->attachments as $attachment)
                <li class="flex items-center gap-3 px-3 py-2">
                    <a href="{{ route('attachments.show', $attachment) }}" target="_blank" rel="noopener"
                       class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-slate-400">
                        @if ($attachment->isImage())
                            <img src="{{ route('attachments.show', $attachment) }}" alt="" loading="lazy" class="size-full object-cover">
                        @else
                            <x-icon name="paperclip" class="size-4" />
                        @endif
                    </a>

                    <div class="min-w-0 flex-1">
                        <a href="{{ route('attachments.show', $attachment) }}" target="_blank" rel="noopener"
                           class="block truncate text-sm font-medium hover:underline">{{ $attachment->original_name }}</a>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $attachment->humanSize() }} ・ {{ $attachment->uploaderName() }} ・
                            <time datetime="{{ $attachment->created_at->toIso8601String() }}">{{ $attachment->created_at->diffForHumans() }}</time>
                        </p>
                    </div>

                    <a href="{{ route('attachments.show', ['attachment' => $attachment, 'download' => 1]) }}"
                       aria-label="{{ $attachment->original_name }} をダウンロード" title="ダウンロード"
                       class="grid size-8 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white">
                        <x-icon name="download" class="size-4" />
                    </a>

                    @can('delete', $attachment)
                        <form action="{{ route('attachments.destroy', [$task, $attachment]) }}" method="POST"
                              data-confirm="「{{ $attachment->original_name }}」を削除しますか？本文に貼った画像も表示されなくなります。">
                            @csrf
                            @method('DELETE')
                            <button type="submit" aria-label="{{ $attachment->original_name }} を削除" title="削除"
                                    class="grid size-8 place-items-center rounded-md text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400">
                                <x-icon name="trash" class="size-4" />
                            </button>
                        </form>
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif

    @can('create', [\App\Models\Attachment::class, $task])
        <form action="{{ route('attachments.store', $task) }}" method="POST" enctype="multipart/form-data"
              class="flex flex-col gap-2 rounded-xl border border-dashed border-slate-300 px-4 py-4 text-sm sm:flex-row sm:items-center dark:border-slate-600"
              data-attachment-form>
            @csrf
            <p class="flex-1 text-slate-500 dark:text-slate-400">
                ここにファイルをドロップ、またはエディタに画像を貼り付け
                <span class="block text-xs">画像・PDF・Office 文書・テキスト・ZIP（{{ \Illuminate\Support\Number::fileSize(config('attachments.max_size') * 1024) }} まで）</span>
            </p>
            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 font-medium hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-white/5">
                <x-icon name="paperclip" class="size-4" /> ファイルを選ぶ
                <input type="file" name="file" required class="sr-only" data-attachment-input
                       accept="{{ collect(config('attachments.extensions'))->map(fn ($ext) => ".{$ext}")->implode(',') }}">
            </label>
            {{-- JS が動けば、選んだ瞬間に送るので押す必要はない（CSS で隠す） --}}
            <button type="submit" class="btn-primary px-3 py-2" data-attachment-submit>添付する</button>
        </form>
        <p class="mt-2 hidden text-sm text-slate-500 dark:text-slate-400" data-attachment-progress aria-live="polite"></p>
        <x-input-error :messages="$errors->get('file')" />
    @elseif ($task->attachments->isEmpty())
        <p class="text-sm text-slate-400 dark:text-slate-500">添付ファイルはありません。</p>
    @endcan
</section>
