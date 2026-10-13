<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_video_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->uuid('viewer_key');
            $table->date('viewed_on');
            $table->timestamp('created_at');
            $table->unique(['evento_id', 'viewer_key', 'viewed_on']);
            $table->index(['evento_id', 'viewed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_video_views');
    }
};
