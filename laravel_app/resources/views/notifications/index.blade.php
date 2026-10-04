@extends('layouts.app')

@section('title', '通知')

@php
    // 種類ごとのアイコン。色は使わず形で見分ける（未読の強調に色を取っておく）
    $icons = ['assigned' => 'user', 'transitioned' => 'arrow-right', 'commented' => 'comment', 'mentioned' => 'at'];
@endphp

@section('content')
    <div class="mb-5 flex flex-wrap items-end gap-3">
        <div>
            <h1 class="text-lg font-semibold tracking-tight">通知</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                担当になった課題・メンションされた課題と、ウォッチしている課題の変更が届きます。
            </p>
        </div>

        @if (auth()->user()->unreadNotifications()->exists())
            <form action="{{ route('notifications.read-all') }}" method="POST" class="ml-auto">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5">
                    <x-icon name="check" class="size-4" /> すべて既読にする
                </button>
            </form>
        @endif
    </div>

    <div class="card overflow-hidden">
        @forelse ($notifications as $notification)
            @php($data = $notification->data)
            <a href="{{ route('notifications.show', $notification->id) }}"
               @class([
                   'flex gap-3 border-b border-slate-100 px-4 py-3 transition last:border-b-0 hover:bg-slate-50 dark:border-white/5 dark:hover:bg-white/5',
                   'bg-brand-50/60 dark:bg-brand-500/5' => $notification->unread(),
               ])>
                <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-slate-400">
                    <x-icon :name="$icons[$data['kind']] ?? 'bell'" class="size-4" />
                </span>

                <span class="min-w-0 flex-1">
                    <span class="flex items-baseline gap-2 text-sm">
                        <span class="shrink-0 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $data['issue_key'] }}</span>
                        <span class="truncate font-medium">{{ $data['issue_title'] }}</span>
                    </span>
                    <span class="mt-0.5 block text-sm text-slate-600 dark:text-slate-300">{{ $data['message'] }}</span>
                    @if (! empty($data['excerpt']))
                        <span class="mt-1 block truncate text-xs text-slate-500 dark:text-slate-400">{{ $data['excerpt'] }}</span>
                    @endif
                </span>

                <span class="flex shrink-0 flex-col items-end gap-1.5 text-xs text-slate-400 dark:text-slate-500">
                    <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                    @if ($notification->unread())
                        <span class="size-2 rounded-full bg-brand-600 dark:bg-brand-400"></span>
                        <span class="sr-only">未読</span>
                    @endif
                </span>
            </a>
        @empty
            <x-empty-state title="通知はありません"
                           description="課題の担当になったり、ウォッチしている課題が動いたりすると、ここに届きます。" />
        @endforelse
    </div>

    <div class="mt-4">{{ $notifications->links() }}</div>
@endsection
