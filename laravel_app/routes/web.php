<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Task\TaskCompletionController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('welcome');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::resource('tasks', TaskController::class);
    // 一覧から 1 クリックで完了状態を切り替えるための専用ルート
    Route::patch('tasks/{task}/completion', TaskCompletionController::class)->name('tasks.completion');
});
