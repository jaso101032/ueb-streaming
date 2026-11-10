<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Subtítulos WebVTT opcionales para grabaciones, videos programados y publicidad (RNF-11). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['transmisiones', 'scheduled_videos', 'institutional_ads'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->string('subtitles_path')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['transmisiones', 'scheduled_videos', 'institutional_ads'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('subtitles_path'));
        }
    }
};
