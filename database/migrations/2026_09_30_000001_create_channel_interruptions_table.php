<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_interruptions', function (Blueprint $table) {
            $table->id();
            // Preserve the channel clock even if an old event is removed.
            $table->unsignedBigInteger('transmision_id')->index();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_interruptions');
    }
};
