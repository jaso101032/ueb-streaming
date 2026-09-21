<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transmisiones', function (Blueprint $table) {
            $table->string('recording_status', 24)->default('idle')->index();
            $table->string('publisher_id', 100)->nullable();
            $table->timestamp('last_signal_at')->nullable();
            $table->string('recording_file')->nullable();
            $table->timestamp('recording_ready_at')->nullable();
            $table->timestamp('recording_attempt_at')->nullable();
            $table->unsignedSmallInteger('recording_attempts')->default(0);
            $table->text('recording_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transmisiones', fn (Blueprint $table) => $table->dropColumn([
            'recording_status', 'publisher_id', 'last_signal_at', 'recording_file',
            'recording_ready_at', 'recording_attempt_at', 'recording_attempts', 'recording_error',
        ]));
    }
};
