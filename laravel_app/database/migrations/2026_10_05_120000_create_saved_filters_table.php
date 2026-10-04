<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M15: 保存した絞り込み条件（個人ごと）。
 *
 * 条件は一覧の URL のクエリ文字列をそのまま JSON で持つ。
 * 絞り込みの項目が増えても、テーブルを変えずに保存できる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->json('query');
            $table->timestamps();

            // 同じ名前が 2 つ並ぶと、どちらを押せばよいか分からない
            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_filters');
    }
};
