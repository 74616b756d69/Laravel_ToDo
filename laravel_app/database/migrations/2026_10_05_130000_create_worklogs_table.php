<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M16: 作業時間の記録。
 *
 * 見積もり（課題にかかると見込んだ時間）は課題の列に、
 * 実績（誰がいつ何分働いたか）は 1 回ごとの行に持つ。
 * 実績を課題の列（合計）にしないのは、人ごと・日ごとに集計したいため。
 * 時間はすべて分の整数で持つ（小数の時間は丸め誤差で合計がずれる）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('original_estimate_minutes')->nullable()->after('story_points');
        });

        Schema::create('worklogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained('tasks')->cascadeOnDelete();
            // 退会しても実績は課題の記録として残す
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('minutes');
            // 作業した日。記録した日（created_at）とは別。昨日の分を今日つけることがある
            $table->date('worked_on');
            $table->string('comment', 255)->nullable();
            $table->timestamps();

            $table->index(['issue_id', 'worked_on']);
            // 「今週だれが何時間」の集計用
            $table->index(['user_id', 'worked_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worklogs');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('original_estimate_minutes');
        });
    }
};
