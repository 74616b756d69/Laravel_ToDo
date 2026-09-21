<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\DemoLoginController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BacklogController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Issue\BrowseController;
use App\Http\Controllers\Issue\CommentController;
use App\Http\Controllers\Issue\IssueLinkController;
use App\Http\Controllers\Project\ProjectMemberController;
use App\Http\Controllers\Project\ProjectSwitchController;
use App\Http\Controllers\Project\StatusController;
use App\Http\Controllers\Project\WorkflowTransitionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Sprint\SprintController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\Task\AssigneeController;
use App\Http\Controllers\Task\IssueTypeController;
use App\Http\Controllers\Task\QuickAddController;
use App\Http\Controllers\Task\SubtaskController;
use App\Http\Controllers\Task\TaskCompletionController;
use App\Http\Controllers\Task\TransitionController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('welcome');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // デモアカウントでのワンクリックログイン（config/demo.php で無効化できる）
    Route::post('login/demo', DemoLoginController::class)->name('login.demo');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ヘッダーの検索窓。キーなら /browse、それ以外は一覧のキーワード検索へ振り分ける
    Route::get('search', SearchController::class)->name('search');

    // 課題キーで課題を開く（/browse/PROJ-123）。詳細の URL は /tasks/{id} のまま
    Route::get('browse/{key}', BrowseController::class)
        ->where('key', '[A-Za-z]{2,10}-[0-9]+')
        ->name('browse');

    // プロジェクト（Phase 1: 器とメンバーのみ。課題はまだ既存のタスク側にある）
    // 切り替えは projects/{project} に飲み込まれないよう resource より先に置く
    Route::patch('projects/current', ProjectSwitchController::class)->name('projects.switch');
    Route::resource('projects', ProjectController::class)->except(['show']);
    Route::post('projects/{project}/members', [ProjectMemberController::class, 'store'])
        ->name('projects.members.store');

    // ワークフロー設定（管理者のみ）。
    // scopeBindings で、URL の {status} / {transition} は {project} 配下のものしか解決しない
    Route::prefix('projects/{project}')->name('projects.')->scopeBindings()->group(function () {
        Route::post('statuses', [StatusController::class, 'store'])->name('statuses.store');
        Route::put('statuses/{status}', [StatusController::class, 'update'])->name('statuses.update');
        Route::patch('statuses/{status}/move', [StatusController::class, 'move'])->name('statuses.move');
        // 削除は「残った課題をどこへ送るか」を選んでから実行する 2 段構え
        Route::get('statuses/{status}/delete', [StatusController::class, 'confirmDelete'])->name('statuses.delete');
        Route::delete('statuses/{status}', [StatusController::class, 'destroy'])->name('statuses.destroy');

        Route::post('transitions', [WorkflowTransitionController::class, 'store'])->name('transitions.store');
        Route::delete('transitions/{transition}', [WorkflowTransitionController::class, 'destroy'])
            ->name('transitions.destroy');
    });

    // バックログとスプリント
    Route::get('backlog', [BacklogController::class, 'index'])->name('backlog');
    Route::patch('backlog/{task}', [BacklogController::class, 'move'])->name('backlog.move');

    Route::post('sprints', [SprintController::class, 'store'])->name('sprints.store');
    Route::put('sprints/{sprint}', [SprintController::class, 'update'])->name('sprints.update');
    Route::delete('sprints/{sprint}', [SprintController::class, 'destroy'])->name('sprints.destroy');
    Route::patch('sprints/{sprint}/start', [SprintController::class, 'start'])->name('sprints.start');
    // 完了は「残った課題をどこへ送るか」を選んでから実行する 2 段構え
    Route::get('sprints/{sprint}/complete', [SprintController::class, 'confirmComplete'])->name('sprints.complete');
    Route::post('sprints/{sprint}/complete', [SprintController::class, 'complete'])->name('sprints.complete.store');

    // カンバンボード
    Route::get('board', [BoardController::class, 'index'])->name('board');
    Route::patch('board/{task}', [BoardController::class, 'move'])->name('board.move');

    // 1 行入力からのクイック追加
    Route::post('tasks/quick', QuickAddController::class)
        ->middleware('throttle:60,1')
        ->name('tasks.quick');

    Route::resource('tasks', TaskController::class);
    // 一覧から 1 クリックで完了状態を切り替えるための専用ルート
    Route::patch('tasks/{task}/completion', TaskCompletionController::class)->name('tasks.completion');

    // 詳細画面からのインライン操作。編集フォームを開かずに 1 リクエストで変える
    Route::patch('tasks/{task}/transition', TransitionController::class)->name('tasks.transition');
    Route::patch('tasks/{task}/assignee', AssigneeController::class)->name('tasks.assignee');
    Route::patch('tasks/{task}/type', IssueTypeController::class)->name('tasks.type');

    // コメント（課題に従属するのでネストする）。履歴は不変なのでルートを持たない。
    // 投稿はサニタイズ（HTMLPurifier）が重いので、連投に上限を設ける
    Route::post('tasks/{task}/comments', [CommentController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('comments.store');
    Route::put('tasks/{task}/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('tasks/{task}/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    // リンクされた作業項目。親子とは別で、関連づけても相手は一覧に残る
    Route::post('tasks/{task}/links', [IssueLinkController::class, 'store'])->name('links.store');
    Route::delete('tasks/{task}/links/{link}', [IssueLinkController::class, 'destroy'])->name('links.destroy');

    // サブタスク（タスクに従属するのでネストする）
    Route::post('tasks/{task}/subtasks', [SubtaskController::class, 'store'])->name('subtasks.store');
    Route::patch('tasks/{task}/subtasks/{subtask}', [SubtaskController::class, 'toggle'])->name('subtasks.toggle');
    // 「外す」と「削除」は別物。引き込んだ既存課題を消してしまわないよう分ける
    Route::patch('tasks/{task}/subtasks/{subtask}/detach', [SubtaskController::class, 'detach'])
        ->name('subtasks.detach');
    Route::delete('tasks/{task}/subtasks/{subtask}', [SubtaskController::class, 'destroy'])->name('subtasks.destroy');

    Route::resource('tags', TagController::class)->only(['index', 'store', 'update', 'destroy']);
});
