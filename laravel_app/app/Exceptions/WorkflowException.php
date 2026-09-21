<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * ワークフローの設定が、守るべき前提を壊すときの例外。
 *
 * 前提は 2 つだけ:
 *  - ステータスが 1 つ以上ある（initialStatus() が firstOrFail なので、空にすると全画面が落ちる）
 *  - done カテゴリのステータスが 1 つ以上ある（doneStatus() も同じ）
 *
 * 入口のバリデーションでも弾くが、最後の砦としてここでも見る。
 */
class WorkflowException extends RuntimeException
{
    public static function lastStatus(string $name): self
    {
        return new self(
            "「{$name}」はこのプロジェクト唯一のステータスなので削除できません。"
            .'先に別のステータスを追加してください。',
        );
    }

    public static function lastDoneStatus(string $name): self
    {
        return new self(
            "「{$name}」が無くなると「完了」として扱うステータスが 1 つも無くなります。"
            .'先に別のステータスを完了カテゴリにしてください。',
        );
    }

    public static function destinationRequired(string $name, int $count): self
    {
        return new self("「{$name}」には課題が {$count} 件残っています。移送先を選んでから削除してください。");
    }

    public static function invalidDestination(): self
    {
        return new self('移送先には、同じプロジェクトの別のステータスだけを指定できます。');
    }

    public static function pointlessTransition(): self
    {
        return new self('同じステータスへの遷移は登録できません。');
    }

    public static function duplicatedTransition(): self
    {
        return new self('その遷移はすでに登録されています。');
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withErrors(['workflow' => $this->getMessage()]);
    }
}
