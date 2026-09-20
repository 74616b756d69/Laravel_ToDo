<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M10: 課題どうしの関連（リンクされた作業項目）。
 *
 * 新しいテーブルを足すだけ。tasks には触らないので、
 * 自己参照の外部キーにまつわる SQLite の作り直し問題は起きない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_links', function (Blueprint $table) {
            $table->id();
            // 張った側 / 張られた側。向きのある種別（ブロックなど）で意味を持つ
            $table->foreignId('source_issue_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('target_issue_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('type', 16);
            $table->timestamps();

            // 同じ相手に同じ種別で二重に張らせない
            $table->unique(['source_issue_id', 'target_issue_id', 'type']);
            // 詳細画面は両方向から引くので、どちらにも索引を張る
            $table->index(['source_issue_id', 'type']);
            $table->index(['target_issue_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_links');
    }
};
