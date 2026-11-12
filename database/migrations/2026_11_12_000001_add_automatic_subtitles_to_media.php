<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subtítulos automáticos (transcripción con Whisper) para grabaciones, videos
 * programados y publicidad. El directo no se transcribe.
 *
 * subtitles_source: 'auto' (generados) o 'manual' (subidos por el personal).
 * subtitles_status: null (aún no procesado), pending, processing, ready,
 * failed, no_speech (no se detectó voz) o skipped (el personal los quitó y no
 * se deben volver a generar solos).
 */
return new class extends Migration
{
    private const TABLES = ['transmisiones', 'scheduled_videos', 'institutional_ads'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('subtitles_source', 10)->nullable();
                $t->string('subtitles_status', 20)->nullable();
                $t->unsignedTinyInteger('subtitles_progress')->nullable();
                $t->string('subtitles_error')->nullable();
                $t->unsignedTinyInteger('subtitles_attempts')->default(0);
                $t->timestamp('subtitles_attempt_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['subtitles_source', 'subtitles_status',
                'subtitles_progress', 'subtitles_error', 'subtitles_attempts', 'subtitles_attempt_at']));
        }
    }
};
