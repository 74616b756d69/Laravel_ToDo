<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 親子の紐づけが成り立たないときの例外。
 */
class IssueHierarchyException extends RuntimeException
{
    public static function notFound(string $input): self
    {
        return new self("「{$input}」に一致する課題がこのプロジェクトに見つかりません。");
    }

    public static function itself(): self
    {
        return new self('課題を自分自身のサブタスクにはできません。');
    }

    public static function parentIsChild(string $parentKey): self
    {
        return new self("{$parentKey} はすでに他の課題のサブタスクです。階層は 1 段までです。");
    }

    public static function childHasChildren(string $childKey, int $count): self
    {
        return new self(
            "{$childKey} は {$count} 件のサブタスクを持っています。"
            .'階層は 1 段までなので、先にそちらを外してください。'
        );
    }

    public static function alreadyAttached(string $childKey, string $parentKey): self
    {
        return new self("{$childKey} はすでに {$parentKey} のサブタスクです。");
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withErrors(['title' => $this->getMessage()])->withInput();
    }
}
