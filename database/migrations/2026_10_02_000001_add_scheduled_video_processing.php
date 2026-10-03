<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_videos', function (Blueprint $t) {
            $t->string('processing_status')->default('ready')->index();
            $t->string('source_path')->nullable();
            $t->unsignedBigInteger('source_bytes')->nullable();
            $t->unsignedBigInteger('optimized_bytes')->nullable();
            $t->timestamp('processing_started_at')->nullable();
            $t->timestamp('ready_at')->nullable();
            $t->string('processing_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_videos', fn (Blueprint $t) => $t->dropColumn(['processing_status', 'source_path', 'source_bytes', 'optimized_bytes', 'processing_started_at', 'ready_at', 'processing_error']));
    }
};
