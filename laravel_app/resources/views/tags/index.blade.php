@extends('layouts.app')

@section('title', 'タグ管理')

@section('content')
    <div class="animate-rise space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">タグ管理</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                タスクを分類するタグを作成・編集できます。
            </p>
        </div>

        {{-- 作成フォーム --}}
        <form action="{{ route('tags.store') }}" method="POST" class="card space-y-4 p-5">
            @csrf
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="name" class="field-label">タグ名</label>
                    <input id="name" name="name" type="text" maxlength="30" required
                           value="{{ old('name') }}" placeholder="例：仕事" class="field">
                </div>
                <button type="submit"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">
                    <x-icon name="plus" class="size-4" /> 作成
                </button>
            </div>

            <div>
                <span class="field-label">色</span>
                <div class="flex flex-wrap gap-2">
                    @foreach (\App\Enums\TagColor::cases() as $color)
                        <label class="cursor-pointer">
                            <input type="radio" name="color" value="{{ $color->value }}" class="peer sr-only"
                                   @checked(old('color', 'slate') === $color->value)>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium ring-1 ring-inset transition
                                         opacity-50 peer-checked:opacity-100 peer-checked:ring-2 {{ $color->badgeClasses() }}">
                                <span class="size-2 rounded-full {{ $color->swatchClasses() }}"></span>
                                {{ $color->label() }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <x-input-error :messages="$errors->get('name')" />
            <x-input-error :messages="$errors->get('color')" />
        </form>

        {{-- 一覧 --}}
        <div class="card overflow-hidden">
            @if ($tags->isEmpty())
                <x-empty-state title="タグがありません" description="よく使う分類をタグにしておくと、絞り込みが一段と楽になります。" />
            @else
                <ul class="divide-y divide-slate-100 dark:divide-white/5">
                    @foreach ($tags as $tag)
                        <li class="group flex flex-wrap items-center gap-3 px-4 py-3">
                            <form action="{{ route('tags.update', $tag) }}" method="POST"
                                  class="flex flex-1 flex-wrap items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name" value="{{ $tag->name }}" maxlength="30" required
                                       class="field w-40 py-1.5 text-sm">

                                <select name="color" class="field w-32 py-1.5 text-sm">
                                    @foreach (\App\Enums\TagColor::options() as $value => $label)
                                        <option value="{{ $value }}" @selected($tag->color->value === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>

                                <button type="submit"
                                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium transition hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-white/5">
                                    保存
                                </button>
                            </form>

                            <a href="{{ route('tasks.index', ['tag' => $tag->id]) }}"
                               class="text-xs text-slate-500 hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">
                                {{ $tag->tasks_count }} 件のタスク
                            </a>

                            <form action="{{ route('tags.destroy', $tag) }}" method="POST"
                                  data-confirm="タグ「{{ $tag->name }}」を削除します。タスクからも外れます。よろしいですか？">
                                @csrf
                                @method('DELETE')
                                <button type="submit" aria-label="タグを削除"
                                        class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10">
                                    <x-icon name="trash" class="size-4" />
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
