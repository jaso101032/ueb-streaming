<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calidades adaptativas (HLS: 1080p, 720p, 480p y 360p) para grabaciones y
 * videos programados. El MP4 se conserva como respaldo y para descargas.
 *
 * renditions_status: null (aún no procesado), pending, processing, ready o failed.
 * renditions_path: carpeta en el disco local con master.m3u8.
 * renditions_levels: calidades generadas, p. ej. [{"name":"720p","width":1280,"height":720}].
 */
return new class extends Migration
{
    private const TABLES = ['transmisiones', 'scheduled_videos'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('renditions_path')->nullable();
                $t->string('renditions_status', 20)->nullable();
                $t->unsignedTinyInteger('renditions_progress')->nullable();
                $t->string('renditions_error')->nullable();
                $t->unsignedTinyInteger('renditions_attempts')->default(0);
                $t->timestamp('renditions_attempt_at')->nullable();
                $t->json('renditions_levels')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['renditions_path', 'renditions_status', 'renditions_progress',
                'renditions_error', 'renditions_attempts', 'renditions_attempt_at', 'renditions_levels']));
        }
    }
};
