<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M7: 旧 tasks.status を落とす。破壊的な操作はここだけ。
 *
 * これを適用すると workflows:rollback が使えなくなる。
 * バックアップを取ってから単独でデプロイすること。
 */
return new class extends Migration
{
    public function up(): void
    {
        // 旧 (project_id, status) は (project_id, status_id) が引き継いでいる。
        // 参照を残したままカラムを落とすと SQLite が壊れたインデックスを検出して失敗する
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'status']);
        });

        $this->withoutParentLinks(function () {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        });
    }

    /**
     * 器だけは戻せるが、中身は戻らない。
     * ロールバックしたら status_id から復元するか、バックアップから戻すこと。
     */
    public function down(): void
    {
        $this->withoutParentLinks(function () {
            Schema::table('tasks', function (Blueprint $table) {
                $table->string('status', 16)->default('todo')->after('issue_type');
            });
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['project_id', 'status']);
        });
    }

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
