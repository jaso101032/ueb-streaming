<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_videos', function (Blueprint $table) {
            $table->unsignedTinyInteger('processing_progress')->default(0);
            $table->timestamp('processing_heartbeat_at')->nullable();
        });
        Schema::table('institutional_ads', function (Blueprint $table) {
            $table->unsignedTinyInteger('processing_progress')->default(0);
            $table->timestamp('processing_heartbeat_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('institutional_ads', function (Blueprint $table) {
            $table->dropColumn(['processing_progress', 'processing_heartbeat_at']);
        });
        Schema::table('scheduled_videos', function (Blueprint $table) {
            $table->dropColumn(['processing_progress', 'processing_heartbeat_at']);
        });
    }
};
