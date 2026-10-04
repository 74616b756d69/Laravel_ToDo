<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * アプリ内通知の一覧と既読処理。
 *
 * 通知は自分宛てのものしか触れない。id（UUID）を差し替えられても、
 * $request->user()->notifications() から引くので他人の通知は 404 になる。
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    /**
     * 通知を開く。既読にしてから課題へ送る。
     *
     * 行き先は課題の id の入口（tasks.legacy）。課題が消された・見えなくなった場合は
     * そちらが 404 を返すので、ここで課題の権限を判断し直す必要はない。
     */
    public function show(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect()->route('tasks.legacy', $notification->data['issue_id']);
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->route('notifications.index')->with('status', 'すべて既読にしました。');
    }
}
