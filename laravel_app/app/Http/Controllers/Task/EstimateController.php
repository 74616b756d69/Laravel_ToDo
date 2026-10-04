<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Support\Duration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 詳細画面での見積もり時間の変更。「3h」「1d」のように書ける。空なら未設定に戻る。
 *
 * ストーリーポイント（相対的な大きさ）とは別物。こちらは実績と比べるための時間。
 */
class EstimateController extends Controller
{
    public function __invoke(Request $request, Issue $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $input = trim((string) $request->input('estimate'));
        $minutes = Duration::parse($input);

        if ($input !== '' && $minutes === null) {
            throw ValidationException::withMessages([
                'estimate' => '見積もり時間は「3h」「1h30m」「1d」のように書いてください。',
            ]);
        }

        // 見積もりは課題全体ぶんなので、1 件の作業記録より大きくてよい（上限は 1000 時間）
        if ($minutes !== null && $minutes > 1000 * 60) {
            throw ValidationException::withMessages(['estimate' => '見積もり時間が大きすぎます。']);
        }

        $task->update(['original_estimate_minutes' => $minutes]);

        return back()->with('status', $minutes === null
            ? "{$task->key()} の見積もり時間を外しました。"
            : "{$task->key()} の見積もり時間を ".Duration::format($minutes).' にしました。');
    }
}
