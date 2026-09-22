<?php

namespace App\Http\Controllers\Task;

use App\Enums\TaskPriority;
use App\Http\Controllers\Controller;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 詳細画面での優先度の変更。変更は履歴にも残る（ActivityField::Priority）。
 */
class PriorityController extends Controller
{
    public function __invoke(Request $request, Issue $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate(
            ['priority' => ['required', Rule::enum(TaskPriority::class)]],
            attributes: ['priority' => '優先度'],
        );

        $task->update($validated);

        return back()->with('status', "{$task->key()} の優先度を{$task->priority->label()}にしました。");
    }
}
