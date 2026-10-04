<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M14: Webhook（外部サービスへの通知）と、その配信記録。
 *
 * 配信は 1 回ごとに行を残す。相手が落ちていた・署名を間違えていた、を
 * 送った側の画面から確かめられないと、連携の不具合は調べようがない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('url', 2048);
            // generic（署名付きの JSON）/ slack（Incoming Webhook の書式）
            $table->string('format', 16);
            // 暗号化して保存する（App\Models\Webhook の casts）。長さは暗号文ぶん取る
            $table->text('secret');
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event', 40);
            // 送る中身はイベントの時点で写し取る。再送しても同じものが届く
            $table->json('payload');
            // pending / succeeded / failed
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_id', 'created_at']);
            // 古い記録の掃除（MassPrunable）用
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};
