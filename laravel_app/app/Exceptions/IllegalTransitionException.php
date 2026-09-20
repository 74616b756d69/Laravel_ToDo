<?php

namespace App\Exceptions;

use App\Models\Status;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * ワークフローで許可されていないステータス変更。
 *
 * ボードからの操作（JSON）では 422 とメッセージを返し、
 * 画面側でカードを元の位置に戻して理由を出す。
 */
class IllegalTransitionException extends RuntimeException
{
    public function __construct(
        public readonly Status $from,
        public readonly Status $to,
    ) {
        parent::__construct(sprintf(
            '「%s」から「%s」へは変更できません。このプロジェクトのワークフローで許可されていない遷移です。',
            $from->name,
            $to->name,
        ));
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'from' => $this->from->name,
                'to' => $this->to->name,
            ], 422);
        }

        return back()->withErrors(['status' => $this->getMessage()]);
    }
}
