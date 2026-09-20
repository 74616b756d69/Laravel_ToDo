<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5: 固定 3 ステータスを、プロジェクトごとのワークフローに置き換える。
 *
 * 追加だけ。tasks.status_id は nullable のまま入れて、
 * 中身は workflows:install で埋める。既存コードは無変更で動く。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            // 名前は自由に決められるので、意味づけはこのカテゴリが持つ
            $table->string('category', 16);
            // レーンの並び順。いちばん小さいものが新規課題の初期ステータスになる
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'name']);
            $table->index(['project_id', 'position']);
        });

        Schema::create('transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // null は「どのステータスからでも」。Jira の global transition にあたる
            $table->foreignId('from_status_id')->nullable()->constrained('statuses')->cascadeOnDelete();
            $table->foreignId('to_status_id')->constrained('statuses')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'from_status_id', 'to_status_id']);
            $table->index(['project_id', 'from_status_id']);
        });

        // 外部キーの追加は SQLite ではテーブル再構築になる。
        // tasks は parent_id で自分自身を cascade 参照しているので、
        // リンクを一時的に外しておかないと子課題が巻き添えで消える
        $this->withoutParentLinks(function () {
            Schema::table('tasks', function (Blueprint $table) {
                // 使用中のステータスを消せないよう restrict にする。
                // 消したい場合は先に課題を移し替えてもらう
                $table->foreignId('status_id')->nullable()->after('issue_type')
                    ->constrained('statuses')->restrictOnDelete();

                $table->index(['project_id', 'status_id']);
            });
        });
    }

    public function down(): void
    {
        $this->withoutParentLinks(function () {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropIndex(['project_id', 'status_id']);
                $table->dropConstrainedForeignId('status_id');
            });
        });

        Schema::dropIfExists('transitions');
        Schema::dropIfExists('statuses');
    }

    /**
     * 親子のリンクを一時的に外してからスキーマ変更を行い、あとで戻す。
     *
     * SQLite のテーブル再構築は「__temp__tasks を作る → 旧 tasks から流し込む →
     * 旧 tasks を drop → rename」の順で走る。このとき __temp__tasks の parent_id は
     * まだ旧 tasks を参照しているため、drop の暗黙 DELETE が ON DELETE CASCADE を
     * 発火させ、コピー済みの子課題を道連れに消してしまう。
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
