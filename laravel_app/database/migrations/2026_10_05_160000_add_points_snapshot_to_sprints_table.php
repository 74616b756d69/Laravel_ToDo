<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M18: ベロシティ用に、スプリントの「約束した量」と「やり終えた量」を写し取っておく。
 *
 * どちらも課題の今の状態からは復元できない。開始後に足した・外した課題や、
 * 完了時に次へ送った課題で、スプリントの中身は後から変わるため。
 * この列ができる前に閉じたスプリントは null のまま（完了分だけ課題から数え直す）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->unsignedInteger('committed_points')->nullable()->after('state');
            $table->unsignedInteger('completed_points')->nullable()->after('committed_points');
        });
    }

    public function down(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->dropColumn(['committed_points', 'completed_points']);
        });
    }
};
