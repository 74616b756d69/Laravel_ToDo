<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * 詳細画面でのタグの付け替え。
 *
 * タグは利用者ごとの持ち物なので、自分が作ったものしか付けられない。
 * 欄ごと送られてくるので、外したいときは空の配列が届く。
 */
class TagController extends Controller
{
    public function __invoke(Request $request, Issue $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate(
            [
                'tags' => ['array'],
                'tags.*' => ['integer', Rule::exists('tags', 'id')->where('user_id', Auth::id())],
            ],
            attributes: ['tags' => 'タグ'],
        );

        $task->tags()->sync(array_map('intval', $validated['tags'] ?? []));

        return back()->with('status', "{$task->key()} のタグを更新しました。");
    }
}
