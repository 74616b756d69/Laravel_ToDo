<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M13: 課題の添付ファイル。
 *
 * ファイルの中身はストレージ（ローカル / S3）に置き、ここには在りかと素性だけを持つ。
 * disk も行ごとに控えるのは、保存先を S3 に切り替えたあとも、
 * それまでにローカルへ置いたファイルを読めるようにするため。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained('tasks')->cascadeOnDelete();
            // 退会しても添付は課題の記録として残す
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('original_name');
            // 送られてきた Content-Type ではなく、中身から判定した値
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index(['issue_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
