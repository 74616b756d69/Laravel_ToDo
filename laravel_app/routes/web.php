<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\DemoLoginController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BacklogController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Issue\AttachmentController;
use App\Http\Controllers\Issue\CommentController;
use App\Http\Controllers\Issue\IssueLinkController;
use App\Http\Controllers\Issue\LegacyUrlController;
use App\Http\Controllers\Issue\WatchController;
use App\Http\Controllers\Issue\WorklogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Project\ProjectMemberController;
use App\Http\Controllers\Project\ProjectSwitchController;
use App\Http\Controllers\Project\StatusController;
use App\Http\Controllers\Project\WebhookController;
use App\Http\Controllers\Project\WorkflowTransitionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SavedFilterController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Sprint\SprintController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\Task\AssigneeController;
use App\Http\Controllers\Task\ContentController;
use App\Http\Controllers\Task\DueDateController;
use App\Http\Controllers\Task\EstimateController;
use App\Http\Controllers\Task\ExportController;
use App\Http\Controllers\Task\ImportController;
use App\Http\Controllers\Task\IssueTypeController;
use App\Http\Controllers\Task\PriorityController;
use App\Http\Controllers\Task\QuickAddController;
use App\Http\Controllers\Task\StoryPointsController;
use App\Http\Controllers\Task\SubtaskController;
// プロジェクト横断のタグ管理（TagController）と名前が並ぶので、課題側は別名で受ける
use App\Http\Controllers\Task\TagController as IssueTagController;
use App\Http\Controllers\Task\TaskCompletionController;
use App\Http\Controllers\Task\TitleController;
use App\Http\Controllers\Task\TransitionController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

// ログイン済みならトップは飛ばして課題一覧へ。紹介ページを見せ続ける理由がない
Route::get('/', fn () => auth()->check() ? redirect()->route('tasks.index') : view('welcome'))->name('welcome');

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

    // アプリ内通知。開くと既読にして課題へ送る
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::get('notifications/{id}', [NotificationController::class, 'show'])->whereUuid('id')->name('notifications.show');

    // ヘッダーの検索窓。キーなら /browse、それ以外は一覧のキーワード検索へ振り分ける
    Route::get('search', SearchController::class)->name('search');

    // REST API の個人アクセストークン
    Route::get('settings/tokens', [ApiTokenController::class, 'index'])->name('settings.tokens');
    Route::post('settings/tokens', [ApiTokenController::class, 'store'])->name('settings.tokens.store');
    Route::delete('settings/tokens/{token}', [ApiTokenController::class, 'destroy'])
        ->whereNumber('token')->name('settings.tokens.destroy');

    // 一覧の絞り込み条件の保存（個人ごと）
    Route::post('saved-filters', [SavedFilterController::class, 'store'])->name('saved-filters.store');
    Route::delete('saved-filters/{savedFilter}', [SavedFilterController::class, 'destroy'])
        ->whereNumber('savedFilter')->name('saved-filters.destroy');

    // 課題詳細。URL は課題キー（/browse/PROJ-123）。
    // 画面でもやりとりでも課題を指すのはキーなので、URL もそれに合わせる。
    // id からキーへの解決は Issue::resolveRouteBinding() が受け持つ。
    Route::get('browse/{task}', [TaskController::class, 'show'])
        ->where('task', '[A-Za-z]{2,10}-[0-9]+')
        ->name('tasks.show');

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

        // Webhook（外部サービスへの通知）。テスト送信と再送は、相手を叩きすぎないよう上限を設ける
        Route::get('webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
        Route::post('webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
        Route::get('webhooks/{webhook}', [WebhookController::class, 'show'])->name('webhooks.show');
        Route::put('webhooks/{webhook}', [WebhookController::class, 'update'])->name('webhooks.update');
        Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');
        Route::post('webhooks/{webhook}/secret', [WebhookController::class, 'rotateSecret'])->name('webhooks.secret');
        Route::post('webhooks/{webhook}/ping', [WebhookController::class, 'ping'])
            ->middleware('throttle:10,1')->name('webhooks.ping');
        Route::post('webhooks/{webhook}/deliveries/{delivery}/redeliver', [WebhookController::class, 'redeliver'])
            ->middleware('throttle:10,1')->name('webhooks.redeliver');

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

    // CSV。エクスポートは一覧の絞り込み条件のまま。インポートはキューで取り込む
    // （resource の tasks/{task} に飲み込まれないよう、その前に置く）
    Route::get('tasks/export', ExportController::class)->middleware('throttle:10,1')->name('tasks.export');
    Route::get('tasks/import', [ImportController::class, 'create'])->name('tasks.import');
    Route::post('tasks/import', [ImportController::class, 'store'])->middleware('throttle:10,1')->name('tasks.import.store');
    Route::get('tasks/import/template', [ImportController::class, 'template'])->name('tasks.import.template');
    Route::get('tasks/import/{import}', [ImportController::class, 'show'])->whereNumber('import')->name('tasks.import.show');

    // 詳細（show）は課題キーの URL に出してあるのでここでは作らない。
    // 編集は詳細画面の項目ごとのインライン更新（下の PATCH 群）に一本化したので、
    // まとめて直すための edit / update も持たない
    Route::resource('tasks', TaskController::class)->except(['show', 'edit', 'update']);

    // 連番で貼られた古い詳細 URL は、キーの URL へ送る（create などの後に置く）
    Route::get('tasks/{id}', LegacyUrlController::class)->whereNumber('id')->name('tasks.legacy');
    // 一覧から 1 クリックで完了状態を切り替えるための専用ルート
    Route::patch('tasks/{task}/completion', TaskCompletionController::class)->name('tasks.completion');

    // 詳細画面からのインライン操作。
    // 課題の書き換えはすべてここを通る。値を押せばその場で入力に変わり、
    // 1 項目の変更が 1 リクエストで閉じる（編集フォームへ移動する経路は持たない）。
    Route::patch('tasks/{task}/transition', TransitionController::class)->name('tasks.transition');
    Route::patch('tasks/{task}/assignee', AssigneeController::class)->name('tasks.assignee');
    Route::patch('tasks/{task}/type', IssueTypeController::class)->name('tasks.type');
    Route::patch('tasks/{task}/title', TitleController::class)->name('tasks.title');
    Route::patch('tasks/{task}/content', ContentController::class)->name('tasks.content');
    Route::patch('tasks/{task}/priority', PriorityController::class)->name('tasks.priority');
    Route::patch('tasks/{task}/due-date', DueDateController::class)->name('tasks.due-date');
    Route::patch('tasks/{task}/story-points', StoryPointsController::class)->name('tasks.story-points');
    Route::patch('tasks/{task}/tags', IssueTagController::class)->name('tasks.tags');
    Route::patch('tasks/{task}/estimate', EstimateController::class)->name('tasks.estimate');

    // 作業時間の記録（実績）。書き直しは持たず、消して記録し直す
    Route::post('tasks/{task}/worklogs', [WorklogController::class, 'store'])->name('worklogs.store');
    Route::delete('tasks/{task}/worklogs/{worklog}', [WorklogController::class, 'destroy'])->name('worklogs.destroy');

    // コメント（課題に従属するのでネストする）。履歴は不変なのでルートを持たない。
    // 投稿はサニタイズ（HTMLPurifier）が重いので、連投に上限を設ける
    Route::post('tasks/{task}/comments', [CommentController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('comments.store');
    Route::put('tasks/{task}/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('tasks/{task}/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    // 添付ファイル。保存はサニタイズと同じく重いので、連投に上限を設ける。
    // 読み出しは課題キーを経由しない（エディタに埋めた画像の URL が、キーの変更で切れないように）
    Route::post('tasks/{task}/attachments', [AttachmentController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('attachments.store');
    Route::delete('tasks/{task}/attachments/{attachment}', [AttachmentController::class, 'destroy'])
        ->name('attachments.destroy');
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])
        ->whereNumber('attachment')
        ->name('attachments.show');

    // リンクされた作業項目。親子とは別で、関連づけても相手は一覧に残る
    Route::post('tasks/{task}/links', [IssueLinkController::class, 'store'])->name('links.store');
    Route::delete('tasks/{task}/links/{link}', [IssueLinkController::class, 'destroy'])->name('links.destroy');

    // ウォッチ（変更を通知で受け取る）
    Route::post('tasks/{task}/watch', [WatchController::class, 'store'])->name('tasks.watch');
    Route::delete('tasks/{task}/watch', [WatchController::class, 'destroy'])->name('tasks.unwatch');

    // サブタスク（タスクに従属するのでネストする）
    Route::post('tasks/{task}/subtasks', [SubtaskController::class, 'store'])->name('subtasks.store');
    Route::patch('tasks/{task}/subtasks/{subtask}', [SubtaskController::class, 'toggle'])->name('subtasks.toggle');
    // 「外す」と「削除」は別物。引き込んだ既存課題を消してしまわないよう分ける
    Route::patch('tasks/{task}/subtasks/{subtask}/detach', [SubtaskController::class, 'detach'])
        ->name('subtasks.detach');
    Route::delete('tasks/{task}/subtasks/{subtask}', [SubtaskController::class, 'destroy'])->name('subtasks.destroy');

    Route::resource('tags', TagController::class)->only(['index', 'store', 'update', 'destroy']);
});
