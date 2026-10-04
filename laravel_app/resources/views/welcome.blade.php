@extends('layouts.app')

@section('title', 'ホーム')

{{--
    未ログインの人だけが見る紹介ページ（ログイン済みなら課題一覧へ送っている）。

    装飾的な見出しや、アイコン付きの特徴カードは置かない。
    この製品が何をするかは、実際の画面と同じ部品（キー・ロゼンジ・担当者）で
    組んだ小さなボードを見せるほうが早く伝わる。
--}}
@section('content')
    @php
        // 見本のボード。データベースは使わず、実画面と同じ部品で見た目だけを組む
        $sample = [
            ['To Do', [
                ['APP-42', 'ログイン画面の文言を見直す', '低'],
                ['APP-45', '通知メールのテンプレート', '中'],
            ]],
            ['In Progress', [
                ['APP-38', 'スプリント完了時の持ち越し', '高'],
            ]],
            ['Done', [
                ['APP-31', '課題キーで URL を引けるようにする', '中'],
            ]],
        ];
    @endphp

    <section class="grid items-center gap-10 py-8 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] lg:py-16">
        <div>
            <h1 class="text-3xl leading-tight font-bold tracking-tight text-balance sm:text-4xl">
                チームの課題とスプリントを、<br class="hidden sm:inline">ひとつの場所で回す。
            </h1>

            <p class="mt-4 max-w-lg text-pretty text-slate-600 dark:text-slate-300">
                課題には <code class="font-mono text-sm">APP-123</code> のようなキーが付き、
                プロジェクトごとに決めたワークフローに沿ってボードの上を進みます。
                バックログからスプリントを組み、進み具合はバーンダウンで確かめられます。
            </p>

            <div class="mt-7 flex flex-wrap items-center gap-3">
                @if (config('demo.enabled'))
                    {{-- 登録せずに中身を見たい人向けの導線をいちばん前に置く --}}
                    <form action="{{ route('login.demo') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-primary px-4 py-2.5">デモで試す</button>
                    </form>
                @endif
                <a href="{{ route('register') }}" class="btn-quiet px-4 py-2.5">アカウントを作成</a>
                <a href="{{ route('login') }}"
                   class="px-2 text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">ログイン</a>
            </div>

            @if (config('demo.enabled'))
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    デモには課題 100 件とスプリント 3 本が入っています。登録は不要です。
                </p>
            @endif
        </div>

        {{-- 見本のボード。装飾ではなく、製品の画面そのものの縮図として置く --}}
        <div aria-hidden="true" class="grid grid-cols-3 gap-2 rounded-lg border border-slate-200 bg-white p-2 dark:border-slate-800 dark:bg-slate-900">
            @foreach ($sample as [$name, $cards])
                <div class="rounded-md bg-slate-100 p-1.5 dark:bg-white/[0.03]">
                    <p class="px-1.5 pt-1 pb-2 text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
                        {{ $name }} <span class="ml-1 font-mono">{{ count($cards) }}</span>
                    </p>
                    <div class="space-y-1.5">
                        @foreach ($cards as [$key, $title, $priority])
                            <div class="rounded-md border border-slate-200 bg-white p-2.5 dark:border-slate-700/80 dark:bg-slate-900">
                                <p class="text-xs leading-snug">{{ $title }}</p>
                                <p class="mt-2 flex items-center gap-1.5">
                                    <span class="font-mono text-[10px] text-slate-500">{{ $key }}</span>
                                    @if ($priority === '高')
                                        <x-icon name="priority-high" class="ml-auto size-3.5 text-rose-500" />
                                    @endif
                                    <span class="{{ $priority === '高' ? '' : 'ml-auto' }} grid size-4 place-items-center rounded-full bg-slate-200 text-[9px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200">A</span>
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- できること。アイコンのカードではなく、具体的な中身を罫線で区切って並べる --}}
    <section class="border-t border-slate-200 py-10 dark:border-slate-800">
        <dl class="grid gap-x-10 gap-y-6 sm:grid-cols-2">
            @foreach ([
                ['ワークフロー', 'ステータスと「どこからどこへ動かせるか」をプロジェクトごとに設定。未着手の課題をいきなりレビューに出す、といった動きを止められます。'],
                ['バックログとスプリント', 'ドラッグで課題をスプリントに積み、ストーリーポイントで量を見積もる。完了時に残った課題は次のスプリントかバックログへ送ります。'],
                ['1 行で起票', '「明日 請求書を送る #経理 !高」と書けば、期限・タグ・優先度を読み取って課題にします。'],
                ['通知とウォッチ', '担当になった課題、ウォッチしている課題の変更やコメントが届きます。自分の操作は通知しません。'],
            ] as [$title, $body])
                <div>
                    <dt class="text-sm font-semibold">{{ $title }}</dt>
                    <dd class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $body }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
@endsection
