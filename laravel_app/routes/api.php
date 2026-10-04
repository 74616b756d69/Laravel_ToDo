<?php

use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\IssueController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ProjectController;
use Illuminate\Support\Facades\Route;

/*
 * REST API（/api/v1）。認証は個人アクセストークン（Authorization: Bearer …）。
 *
 * トークンには read / write の権限を付けて発行する。読むだけの連携に
 * 書き込める鍵を渡さずに済むように。仕様は docs/openapi.yaml。
 *
 * {issue} は課題キー（PROJ-123）。見えない課題は 404（画面と同じく Issue::resolveRouteBinding）。
 */
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])->name('api.')->group(function () {
    Route::middleware('abilities:read')->group(function () {
        Route::get('me', MeController::class)->name('me');
        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/{project:key}', [ProjectController::class, 'show'])->name('projects.show');
        Route::get('issues', [IssueController::class, 'index'])->name('issues.index');
        Route::get('issues/{issue}', [IssueController::class, 'show'])->name('issues.show');
        Route::get('issues/{issue}/comments', [CommentController::class, 'index'])->name('comments.index');
    });

    Route::middleware('abilities:write')->group(function () {
        Route::post('projects/{project:key}/issues', [IssueController::class, 'store'])->name('issues.store');
        Route::patch('issues/{issue}', [IssueController::class, 'update'])->name('issues.update');
        Route::delete('issues/{issue}', [IssueController::class, 'destroy'])->name('issues.destroy');
        Route::post('issues/{issue}/comments', [CommentController::class, 'store'])->name('comments.store');
    });
});
