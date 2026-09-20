<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M4: 破壊的な操作はここだけに隔離する。
 *
 * この 1 本を適用したあとは issues:rollback-migration が使えなくなる。
 * 必ずバックアップを取ってから、単独でデプロイすること。
 *
 * - subtasks … 全行を Subtask 型の課題へ移送済み
 * - tasks.user_id … reporter_id へ移送済み。残すと作成者の真実が 2 箇所になる
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('subtasks');

        $this->withoutParentLinks(function () {
            // 順序が重要。MySQL は外部キーが使っているインデックスを先に落とせず、
            // SQLite は user_id を参照するインデックスを残したまま列を落とせない。
            // 「外部キー → インデックス → 列」の順だけが両方で通る。
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });

            Schema::table('tasks', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'status']);
                $table->dropIndex(['user_id', 'due_date']);
            });

            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        });
    }

    /**
     * 器だけは戻せるが、中身は戻らない。
     * ロールバックしたら必ずバックアップからデータを復元すること。
     */
    public function down(): void
    {
        $this->withoutParentLinks(function () {
            Schema::table('tasks', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')
                    ->constrained()->cascadeOnDelete();

                $table->index(['user_id', 'status']);
                $table->index(['user_id', 'due_date']);
            });
        });

        Schema::create('subtasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('title', 120);
            $table->boolean('is_done')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['task_id', 'position']);
        });
    }

    /**
     * 親子のリンクを一時的に外してからスキーマ変更を行い、あとで戻す。
     *
     * SQLite の外部キー削除はテーブルの作り直しで、順序は
     * 「__temp__tasks を作る → 旧 tasks から流し込む → 旧 tasks を drop → rename」。
     * このとき __temp__tasks の parent_id はまだ旧 tasks を参照しているため、
     * drop の暗黙 DELETE が ON DELETE CASCADE を発火させ、
     * コピー済みの子課題を道連れに消してしまう。
     *
     * PRAGMA foreign_keys はトランザクションの中では効かないので、
     * 外部キーを止めるのではなくリンク自体を外して回避する。
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
