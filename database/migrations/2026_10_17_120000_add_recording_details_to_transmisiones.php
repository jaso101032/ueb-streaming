<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transmisiones', function (Blueprint $table) {
            $table->unsignedTinyInteger('recording_fps_limit')->nullable();
            $table->json('recording_metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transmisiones', fn (Blueprint $table) => $table->dropColumn([
            'recording_fps_limit', 'recording_metadata',
        ]));
    }
};
