<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M8: スプリント。
 *
 * 追加だけなので既存コードは無変更で動く（sprint_id は nullable = バックログ扱い）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->text('goal')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('state', 16)->default('future');

            /*
             * 「同時に active にできるのは 1 つだけ」を DB でも保証する。
             *
             * active のときだけ 1 が入り、それ以外は null。NULL 同士は unique に
             * 引っかからないので、active でないスプリントはいくつでも並べられる。
             * MySQL も SQLite も部分インデックスを同じ形では書けないため、この手を使う。
             */
            $table->unsignedTinyInteger('active_marker')->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'active_marker']);
            $table->unique(['project_id', 'name']);
            $table->index(['project_id', 'state']);
        });

        // 外部キーの追加は SQLite ではテーブル再構築になる。
        // tasks は parent_id で自分自身を cascade 参照しているので、
        // リンクを一時的に外しておかないと子課題が巻き添えで消える
        $this->withoutParentLinks(function () {
            Schema::table('tasks', function (Blueprint $table) {
                // スプリントを消しても課題は残す（バックログへ戻る）
                $table->foreignId('sprint_id')->nullable()->after('status_id')
                    ->constrained('sprints')->nullOnDelete();
                // 見積り。Fibonacci を想定するが、値の制約はアプリ側で持たない
                $table->unsignedSmallInteger('story_points')->nullable()->after('position');

                $table->index(['sprint_id', 'position']);
            });
        });
    }

    public function down(): void
    {
        $this->withoutParentLinks(function () {
            // 順序が重要。MySQL は外部キーが使っているインデックスを先に落とせないので、
            // 「外部キー → インデックス → カラム」の順で外す（M4 と同じ理由）
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropForeign(['sprint_id']);
            });

            Schema::table('tasks', function (Blueprint $table) {
                $table->dropIndex(['sprint_id', 'position']);
            });

            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn(['sprint_id', 'story_points']);
            });
        });

        Schema::dropIfExists('sprints');
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
