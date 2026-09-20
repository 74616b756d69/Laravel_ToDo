<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M9: コメントと履歴。
 *
 * どちらも追加だけ。既存の課題には履歴が無い状態から始まる
 * （過去に遡って記録することはできない）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained('tasks')->cascadeOnDelete();
            // 書いた人が消えてもコメントは残す（議論の流れが欠けるため）
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // 本文は Tiptap の HTML。保存直前に必ずサニタイズする
            $table->text('body');
            // 検索用の平文。課題本文と同じ仕組み
            $table->text('body_text')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['issue_id', 'created_at']);
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained('tasks')->cascadeOnDelete();
            // 誰の操作か。コマンドやシーダーからの変更は null
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('field', 32);
            /*
             * 値は ID ではなく「そのときの表示名」を入れる。
             * スプリントや担当者が後から消えても履歴が読めるようにするため。
             * 監査ログは当時の姿を残すのが正しい。
             */
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();

            // 履歴は不変なので updated_at は持たない
            $table->timestamp('created_at')->nullable();

            $table->index(['issue_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('comments');
    }
};
