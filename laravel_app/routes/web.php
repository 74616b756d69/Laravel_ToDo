<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TopController;
use App\Http\Controllers\LoginController; #ログインコントローラー
use App\Http\Controllers\SignUpController; #サインアップコントローラー
use App\Http\Controllers\TaskController; #タスクコントローラー

Route::get('/', function () {
    return view('welcome');
});

Route::get('/top', [TopController::class, 'index'])->name("top");

// Route::prefix("共通のパス")->group(function() {
// prefixを使うと、共通のパスを持つルーティングをまとめることができる
Route::prefix('sign_up')->group(function () {
    // サインアップフォーム
    Route::get('/', [SignUpController::class, 'index'])->name("sign_up");
    //サインアップ処理
    Route::post('/', [SignUpController::class, 'store'])->name("sign_up.store");
});

Route::prefix('login')->group(function () {
    // ログインフォーム
    Route::get('/', [LoginController::class, 'index'])->name("login");
    // ログイン処理
    Route::post('/', [LoginController::class, 'store'])->name("login.store");
});

//`Route::middleware(`auth`)->group(function () {` 内に入れると許可ルーティングになる
Route::middleware('auth')->group(function () {
    Route::prefix('task')->group(function () {
        // タスク一覧ページ
        Route::get('/', [TaskController::class, 'index'])->name("task");
        // タスク作成ページ
        Route::get('/create', [TaskController::class, 'create'])->name("task.create");
        // タスク作成処理
        Route::post('/', [TaskController::class, 'store'])->name("task.store");
        //タスク詳細ページ
        //波括弧で囲んだ値は、任意の文字になる
        //例:/task/1, /task/2 などでアクセスできる
        Route::get('/{id}', [TaskController::class, 'show'])->name("task.show");
        //タスク編集ページ
        Route::get('/{id}/edit', [TaskController::class, 'edit'])->name("task.edit");
        //タスク編集処理
        Route::put('/{id}', [TaskController::class, 'update'])->name("task.update");
        //タスクの削除処理
        Route::delete('/{id}', [TaskController::class, 'destroy'])->name("task.destroy");
    });
});
