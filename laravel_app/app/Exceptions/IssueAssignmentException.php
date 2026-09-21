<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * プロジェクトに居ない人を担当者にしようとした。
 *
 * 入口では Rule::exists がプロジェクトで絞って弾くので、ここまで来るのは
 * サービスを直接呼んだ場合だけ。最後の砦として同じ理由を返す。
 */
class IssueAssignmentException extends RuntimeException
{
    public static function notAMember(string $name, string $project): self
    {
        return new self("{$name} は「{$project}」のメンバーではないので担当者にできません。");
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withErrors(['assignee' => $this->getMessage()]);
    }
}
