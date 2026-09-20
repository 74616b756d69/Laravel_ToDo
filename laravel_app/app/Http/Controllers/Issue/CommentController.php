<?php

namespace App\Http\Controllers\Issue;

use App\Http\Controllers\Controller;
use App\Http\Requests\Issue\CommentRequest;
use App\Models\Comment;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;

/**
 * 課題へのコメント。
 *
 * 履歴（Activity）と違ってコメントは編集・削除できる。
 * 言い間違いを直せないと、議論の場としては使いづらいため。
 */
class CommentController extends Controller
{
    public function store(CommentRequest $request, Issue $task): RedirectResponse
    {
        $this->authorize('create', [Comment::class, $task]);

        $task->comments()->make($request->validated())
            ->forceFill(['user_id' => $request->user()->id])
            ->save();

        return redirect()
            ->route('tasks.show', ['task' => $task, 'tab' => 'comments'])
            ->with('status', 'コメントを投稿しました。');
    }

    public function update(CommentRequest $request, Issue $task, Comment $comment): RedirectResponse
    {
        $this->authorize('update', $comment);
        $this->ensureBelongsTo($task, $comment);

        $comment->update([
            'body' => $request->validated()['body'],
            'edited_at' => now(),
        ]);

        return redirect()
            ->route('tasks.show', ['task' => $task, 'tab' => 'comments'])
            ->with('status', 'コメントを更新しました。');
    }

    public function destroy(Issue $task, Comment $comment): RedirectResponse
    {
        $this->authorize('delete', $comment);
        $this->ensureBelongsTo($task, $comment);

        $comment->delete();

        return redirect()
            ->route('tasks.show', ['task' => $task, 'tab' => 'comments'])
            ->with('status', 'コメントを削除しました。');
    }

    /**
     * URL の組み合わせを差し替えて他の課題のコメントを操作されないようにする。
     */
    private function ensureBelongsTo(Issue $task, Comment $comment): void
    {
        abort_unless($comment->issue_id === $task->id, 404);
    }
}
