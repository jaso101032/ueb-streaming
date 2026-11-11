<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Licencia de uso y referencia de la autorización de difusión de cada evento
 * (observación O-10). Los eventos existentes quedan como uso institucional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->string('licencia', 30)->default('institucional')->after('requiere_aprobacion');
            $table->string('autorizacion_referencia', 255)->nullable()->after('licencia');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn(['licencia', 'autorizacion_referencia']);
        });
    }
};
