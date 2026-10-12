<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->uuid('viewer_key');
            $table->timestamp('last_seen_at');
            $table->timestamps();
            $table->unique(['evento_id', 'viewer_key']);
            $table->index(['evento_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_viewers');
    }
};
