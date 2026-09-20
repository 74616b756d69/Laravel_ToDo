<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1: Task を Issue にするためのカラムを足すだけ。既存コードは無変更で動く。
 *
 * ここではまだ NOT NULL にしない。移行コマンド（issues:migrate-from-tasks）で
 * 中身を埋めたあと、M3 で制約を締める。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // 課題番号の採番カウンタ。行ロックして払い出す
            $table->unsignedInteger('last_issue_number')->default(0)->after('description');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')
                ->constrained()->cascadeOnDelete();
            // プロジェクト内の通し番号。表示用キー PROJ-123 の 123
            $table->unsignedInteger('issue_number')->nullable()->after('project_id');
            $table->string('issue_type', 16)->default('task')->after('issue_number');
            // 親課題。サブタスクだけが持つ（階層は 1 段まで）
            $table->foreignId('parent_id')->nullable()->after('issue_type')
                ->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('reporter_id')->nullable()->after('user_id')
                ->constrained('users')->cascadeOnDelete();
            // 担当者が退職してもチケットは残す
            $table->foreignId('assignee_id')->nullable()->after('reporter_id')
                ->constrained('users')->nullOnDelete();

            // 採番の最後の砦。NULL 同士は衝突しないので backfill 前でも張れる
            $table->unique(['project_id', 'issue_number']);
            // 一覧・ボードはサブタスクを除いて引くのでこの並びで張る
            $table->index(['project_id', 'issue_type']);
            $table->index('assignee_id');
            // 旧 (user_id, status) / (user_id, due_date) の後継。M4 で旧側を落とす
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'issue_number']);
            $table->dropIndex(['project_id', 'issue_type']);
            $table->dropIndex(['assignee_id']);
            $table->dropIndex(['project_id', 'status']);
            $table->dropIndex(['project_id', 'due_date']);

            $table->dropConstrainedForeignId('project_id');
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('reporter_id');
            $table->dropConstrainedForeignId('assignee_id');
            $table->dropColumn(['issue_number', 'issue_type']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('last_issue_number');
        });
    }
};
