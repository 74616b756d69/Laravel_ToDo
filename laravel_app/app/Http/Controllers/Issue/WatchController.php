<?php

namespace App\Http\Controllers\Issue;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 課題のウォッチと解除。
 *
 * 見られる課題なら誰でもウォッチできる（viewer を含む）。
 * 読むだけの人こそ、変化を通知で知りたいため。
 */
class WatchController extends Controller
{
    public function store(Request $request, Issue $task): RedirectResponse
    {
        $this->authorize('view', $task);

        $task->watch($request->user());

        return back()->with('status', 'この課題をウォッチしました。変更があると通知が届きます。');
    }

    public function destroy(Request $request, Issue $task): RedirectResponse
    {
        $this->authorize('view', $task);

        $task->unwatch($request->user());

        return back()->with('status', 'ウォッチを解除しました。');
    }
}
