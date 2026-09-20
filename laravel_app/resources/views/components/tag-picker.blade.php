@props(['tags', 'selected' => []])

<div>
    <span class="field-label">タグ</span>

    @if ($tags->isEmpty())
        <p class="text-sm text-slate-500 dark:text-slate-400">
            まだタグがありません。
            <a href="{{ route('tags.index') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">タグを作成する</a>
        </p>
    @else
        <div class="flex flex-wrap gap-2">
            @foreach ($tags as $tag)
                {{-- チェックボックスは隠し、ラベル全体を押せるチップにする --}}
                <label class="cursor-pointer">
                    <input type="checkbox" name="tags[]" value="{{ $tag->id }}" class="peer sr-only"
                           @checked(in_array($tag->id, $selected, true))>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium ring-1 ring-inset transition
                                 opacity-60 grayscale peer-checked:opacity-100 peer-checked:grayscale-0
                                 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-500
                                 {{ $tag->color->badgeClasses() }}">
                        <span class="size-1.5 rounded-full {{ $tag->color->swatchClasses() }}"></span>
                        {{ $tag->name }}
                    </span>
                </label>
            @endforeach
        </div>
    @endif
    <x-input-error :messages="$errors->get('tags')" />
</div>
