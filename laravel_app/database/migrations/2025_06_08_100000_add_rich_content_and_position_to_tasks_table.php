<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // content は HTML を保持するため、検索用にタグを除いた本文を別に持つ
            $table->text('content_text')->nullable()->after('content');
            // カンバン上のレーン内の並び順
            $table->unsignedInteger('position')->default(0)->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['content_text', 'position']);
        });
    }
};
