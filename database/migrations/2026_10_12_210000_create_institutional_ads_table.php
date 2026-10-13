<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('institutional_ads', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('path')->nullable();
            $table->string('source_path')->nullable();
            $table->unsignedInteger('duration')->default(0);
            $table->unsignedBigInteger('source_bytes')->nullable();
            $table->unsignedBigInteger('optimized_bytes')->nullable();
            $table->string('processing_status', 24)->default('pending')->index();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutional_ads');
    }
};
