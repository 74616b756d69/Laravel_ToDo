<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class IssueLinkException extends RuntimeException
{
    public static function notFound(string $input): self
    {
        return new self("「{$input}」に一致する課題がこのプロジェクトに見つかりません。");
    }

    public static function itself(): self
    {
        return new self('課題を自分自身に関連づけることはできません。');
    }

    public static function alreadyLinked(string $key, string $label): self
    {
        return new self("{$key} とは、すでに「{$label}」で関連づけられています。");
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withErrors(['target' => $this->getMessage()])->withInput();
    }
}
