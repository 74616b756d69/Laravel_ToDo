@props(['task'])

{{--
    一覧の 1 行。

    並びはサブタスク行・ボード・バックログと同じ語彙で揃える
    （種別アイコン → キー → 要約 → メタ → 担当者 → ステータス）。
    画面が変わっても同じ位置に同じものがあることが、走り読みできる条件なので、
    ここだけ独自の並びにしない。

    本文の抜粋は出さない。Jira の一覧と同じく、行の役割は「見分けて選ぶ」ことで、
    内容を読むのは詳細画面の仕事だから。抜粋を 1 行足すと表示件数がほぼ半分になる。
--}}
<li class="group flex items-start gap-2.5 px-3 py-2 transition hover:bg-slate-50 dark:hover:bg-white/[0.03]">
    {{-- JS なしでも動く完了トグル。フォーム送信でサーバー側の状態を切り替える --}}
    <form action="{{ route('tasks.completion', $task) }}" method="POST" class="pt-px">
        @csrf
        @method('PATCH')
        <button type="submit"
                aria-label="{{ $task->isCompleted() ? '未着手に戻す' : '完了にする' }}"
                class="grid size-4.5 place-items-center rounded-sm border transition
                       {{ $task->isCompleted()
                            ? 'border-emerald-600 bg-emerald-600 text-white'
                            : 'border-slate-300 text-transparent hover:border-brand-600 hover:text-brand-300 dark:border-slate-600' }}">
            <x-icon name="check" class="size-3" stroke-width="3" />
        </button>
    </form>

    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
            {{-- 種別は形と色で示す。文字にすると要約より先に読まれてしまう --}}
            <x-issue-type-mark :type="$task->issue_type" />

            {{-- 課題キーの見せ方は x-issue-key に集約している --}}
            <a href="{{ route('tasks.show', $task) }}" class="shrink-0">
                <x-issue-key :issue="$task" />
            </a>

            <a href="{{ route('tasks.show', $task) }}"
               class="min-w-0 truncate text-sm transition group-hover:text-brand-700 dark:group-hover:text-brand-300
                      {{ $task->isCompleted() ? 'text-slate-400 line-through dark:text-slate-500' : '' }}">
                {{ $task->title }}
            </a>
        </div>

        {{--
            メタは 1 行にまとめ、要約より小さく弱い表現で置く。
            付いていないものは行ごと出さない（空の行が高さだけ取るのを避ける）。
        --}}
        @if ($task->tags->isNotEmpty() || $task->due_date || ($task->children_count ?? 0) > 0)
            <div class="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-1 pl-6">
                @foreach ($task->tags as $tag)
                    <x-tag-mark :tag="$tag" />
                @endforeach

                <x-due-date :issue="$task" />

                @if (($task->children_count ?? 0) > 0)
                    <span class="w-28">
                        <x-progress-bar :done="$task->done_children_count" :total="$task->children_count" compact />
                    </span>
                @endif
            </div>
        @endif
    </div>

    {{--
        右端は「誰が / どの状態か」に固定する。
        行ごとに位置が動かないので、縦に目を滑らせるだけで担当とステータスを追える。
    --}}
    <div class="flex shrink-0 items-center gap-2 pt-px">
        {{-- 優先度もここに置く。行ごとに位置が動かないほうが、高いものだけを拾いやすい --}}
        <x-priority-mark :priority="$task->priority" />

        @if ($task->story_points !== null)
            <span title="ストーリーポイント"
                  class="grid size-4.5 place-items-center rounded-sm bg-slate-100 font-mono text-[10px] font-medium text-slate-600 tabular-nums dark:bg-white/10 dark:text-slate-300">
                {{ $task->story_points }}
            </span>
        @endif

        <x-avatar :user="$task->assignee" />

        {{-- 狭い画面ではステータスを落とす。要約と担当者のほうが先に要る --}}
        <span class="hidden sm:block">
            <x-badge :classes="$task->status->badgeClasses()" :dot="$task->status->dotClasses()">
                {{ $task->status->name }}
            </x-badge>
        </span>
    </div>
</li>
