<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M6: workflows:install で status_id が埋まったあと、NOT NULL にする。
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->guardAgainstUnmigratedRows();

        $this->withoutParentLinks(function () {
            Schema::table('tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('status_id')->nullable(false)->change();
            });
        });
    }

    public function down(): void
    {
        $this->withoutParentLinks(function () {
            Schema::table('tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('status_id')->nullable()->change();
            });
        });
    }

    private function guardAgainstUnmigratedRows(): void
    {
        $unmigrated = DB::table('tasks')->whereNull('status_id')->count();

        if ($unmigrated > 0) {
            throw new RuntimeException(
                "ステータス未移行の課題が {$unmigrated} 件あります。"
                .'先に php artisan workflows:install を実行してください。',
            );
        }
    }

    /**
     * 親子のリンクを一時的に外してからスキーマ変更を行い、あとで戻す。
     *
     * tasks は parent_id で自分自身を ON DELETE CASCADE 参照しているため、
     * SQLite のテーブル再構築（新テーブル作成 → 旧 tasks を drop → rename）で
     * drop の暗黙 DELETE が CASCADE を発火させ、子課題が消えてしまう。
     * PRAGMA foreign_keys はトランザクション内で効かないので、リンク自体を外す。
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
