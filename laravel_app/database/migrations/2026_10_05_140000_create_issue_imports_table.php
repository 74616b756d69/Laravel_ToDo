<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M17: CSV からの課題の一括取り込み。
 *
 * 取り込みはキューで行うので、進み具合と結果（何行目がなぜだめか）を行に残し、画面で見せる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            // pending / processing / completed / failed
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            // [{ "row": 3, "message": "…" }, …]
            $table->json('errors')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_imports');
    }
};
