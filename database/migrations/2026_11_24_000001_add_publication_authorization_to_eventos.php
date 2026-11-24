<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autorización de difusión de cada evento: quién la emitió, qué cubre, desde
 * cuándo y hasta cuándo rige. Sin una autorización vigente, el evento solo lo
 * ve el personal. Los eventos existentes quedan sin autorización: el
 * administrador debe registrarla para que vuelvan a ser visibles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->string('autorizacion_responsable', 255)->nullable()->after('autorizacion_referencia');
            $table->string('autorizacion_alcance', 500)->nullable()->after('autorizacion_responsable');
            $table->date('autorizacion_fecha')->nullable()->after('autorizacion_alcance');
            $table->date('autorizacion_vigencia')->nullable()->after('autorizacion_fecha');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn(['autorizacion_responsable', 'autorizacion_alcance', 'autorizacion_fecha', 'autorizacion_vigencia']);
        });
    }
};
