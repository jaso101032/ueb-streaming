<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Videos subidos solo para el catálogo: usan la misma conversión, calidades y
 * subtítulos que los de la parrilla, pero nunca se emiten en el canal 24/7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_videos', function (Blueprint $table) {
            $table->boolean('on_channel')->default(true)->after('evento_id');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_videos', function (Blueprint $table) {
            $table->dropColumn('on_channel');
        });
    }
};
