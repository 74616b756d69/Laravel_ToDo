<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M3: 移行コマンドで中身が埋まったあと、制約を締める。
 *
 * 先に未移行の行が残っていないか確認し、残っていれば止める。
 * 半端な状態で NOT NULL を張ると、そこで初めて壊れるため。
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->guardAgainstUnmigratedRows();

        $this->withoutParentLinks(function () {
            Schema::table('tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('project_id')->nullable(false)->change();
                $table->unsignedInteger('issue_number')->nullable(false)->change();
                $table->unsignedBigInteger('reporter_id')->nullable(false)->change();
            });
        });
    }

    public function down(): void
    {
        $this->withoutParentLinks(function () {
            Schema::table('tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('project_id')->nullable()->change();
                $table->unsignedInteger('issue_number')->nullable()->change();
                $table->unsignedBigInteger('reporter_id')->nullable()->change();
            });
        });
    }

    /**
     * 未移行の行が 1 件でもあれば例外を投げてマイグレーションを止める。
     */
    private function guardAgainstUnmigratedRows(): void
    {
        $unmigrated = DB::table('tasks')
            ->whereNull('project_id')
            ->orWhereNull('issue_number')
            ->orWhereNull('reporter_id')
            ->count();

        if ($unmigrated > 0) {
            throw new RuntimeException(
                "未移行のタスクが {$unmigrated} 件あります。"
                .'先に php artisan issues:migrate-from-tasks を実行してください。',
            );
        }
    }

    /**
     * 親子のリンクを一時的に外してからスキーマ変更を行い、あとで戻す。
     *
     * SQLite のカラム定義変更はテーブルの作り直しで、順序は
     * 「__temp__tasks を作る → 旧 tasks から流し込む → 旧 tasks を drop → rename」。
     * このとき __temp__tasks の parent_id はまだ旧 tasks を参照しているため、
     * drop の暗黙 DELETE が ON DELETE CASCADE を発火させ、
     * コピー済みの子課題を道連れに消してしまう。
     *
     * PRAGMA foreign_keys はトランザクションの中では効かないので、
     * 外部キーを止めるのではなくリンク自体を外して回避する。
     * MySQL では単に無害な UPDATE が 2 回走るだけ。
     */
    private function withoutParentLinks(callable $rebuild): void
    {
        $links = DB::table('tasks')->whereNotNull('parent_id')->pluck('parent_id', 'id');

        DB::table('tasks')->whereNotNull('parent_id')->update(['parent_id' => null]);

        $rebuild();

        foreach ($links as $id => $parentId) {
            DB::table('tasks')->where('id', $id)->update(['parent_id' => $parentId]);
        }
    }
};
