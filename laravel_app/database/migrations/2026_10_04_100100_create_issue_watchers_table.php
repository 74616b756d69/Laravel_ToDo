<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M12: 課題のウォッチ（変更を通知で受け取る人）。
 *
 * 起票者・担当者・コメントした人は自動で、それ以外は詳細画面のボタンで加わる。
 * 新しいテーブルを足すだけなので、移行コマンドは要らない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // 同じ人が二重にウォッチしても通知が 2 通届かないようにする
            $table->unique(['issue_id', 'user_id']);
            // 「自分がウォッチしている課題」を引くとき用
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_watchers');
    }
};
