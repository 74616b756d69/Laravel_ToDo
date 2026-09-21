{{-- 作成・編集で共有するフォーム本体。$action / $method / $task を受け取る --}}
<form action="{{ $action }}" method="POST" class="card space-y-5 p-5 sm:p-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <label for="title" class="field-label">タイトル <span class="text-rose-500">*</span></label>
        <input id="title" name="title" type="text" required maxlength="100"
               value="{{ old('title', $task->title) }}"
               placeholder="例：ポートフォリオのREADMEを仕上げる"
               class="field @error('title') border-rose-400 @enderror">
        <x-input-error :messages="$errors->get('title')" />
    </div>

    <div>
        <span class="field-label">内容</span>
        <x-rich-editor :value="old('content', $task->content)" />
        <x-input-error :messages="$errors->get('content')" />
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="issue_type" class="field-label">課題タイプ</label>
            <select id="issue_type" name="issue_type" class="field">
                @foreach (\App\Enums\IssueType::options() as $value => $label)
                    <option value="{{ $value }}"
                            @selected(old('issue_type', $task->issue_type?->value) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('issue_type')" />
        </div>

        <div>
            <label for="assignee" class="field-label">担当者</label>
            {{-- 候補はこのプロジェクトのメンバーだけ。未割り当ても選べる --}}
            <select id="assignee" name="assignee" class="field">
                <option value="">未割り当て</option>
                @foreach ($members as $member)
                    <option value="{{ $member->id }}"
                            @selected((int) old('assignee', $task->assignee_id) === $member->id)>{{ $member->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('assignee')" />
        </div>
    </div>

    <x-tag-picker :tags="$tags" :selected="old('tags', $task->tags->pluck('id')->all())" />

    <div class="grid gap-5 sm:grid-cols-3">
        <div>
            <label for="status" class="field-label">ステータス</label>
            {{-- 現在地と、ワークフローで許可された行き先だけを出す --}}
            <select id="status" name="status" class="field">
                @foreach ($statuses as $status)
                    <option value="{{ $status->id }}"
                            @selected((int) old('status', $task->status?->id) === $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('status')" />
        </div>

        <div>
            <label for="priority" class="field-label">優先度</label>
            <select id="priority" name="priority" class="field">
                @foreach (\App\Enums\TaskPriority::options() as $value => $label)
                    <option value="{{ $value }}" @selected(old('priority', $task->priority?->value) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('priority')" />
        </div>

        <div>
            <label for="due_date" class="field-label">期限</label>
            <input id="due_date" name="due_date" type="date"
                   value="{{ old('due_date', $task->due_date?->format('Y-m-d')) }}" class="field">
            <x-input-error :messages="$errors->get('due_date')" />
        </div>
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-5 dark:border-white/5">
        <a href="{{ $cancelUrl }}"
           class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5">
            キャンセル
        </a>
        <button type="submit"
                class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">
            <x-icon name="check" class="size-4" /> {{ $submitLabel }}
        </button>
    </div>
</form>
