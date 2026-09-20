<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * スプリントの状態遷移が許されないときの例外。
 *
 * バックログ画面からの操作（JSON）では 422 とメッセージを返す。
 */
class SprintException extends RuntimeException
{
    public static function alreadyActive(string $activeName): self
    {
        return new self(
            "「{$activeName}」が進行中です。同時に開始できるスプリントは 1 つだけなので、"
            .'先に進行中のスプリントを完了してください。',
        );
    }

    public static function notStartable(string $name, string $state): self
    {
        return new self("「{$name}」は{$state}なので開始できません。未開始のスプリントだけを開始できます。");
    }

    public static function notCompletable(string $name, string $state): self
    {
        return new self("「{$name}」は{$state}なので完了できません。進行中のスプリントだけを完了できます。");
    }

    public static function invalidDestination(): self
    {
        return new self('残った課題の移送先が正しくありません。未開始のスプリントかバックログを選んでください。');
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withErrors(['sprint' => $this->getMessage()]);
    }
}
