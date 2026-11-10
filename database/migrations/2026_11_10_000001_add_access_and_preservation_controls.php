<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Controles de acceso, publicación, auditoría y conservación.
 *
 * Los valores iniciales conservan el comportamiento existente: los eventos
 * actuales quedan públicos, las grabaciones ya listas quedan aprobadas y los
 * operadores existentes mantienen los permisos que tenían de forma implícita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            // publico: cualquier persona; interno: cuentas con sesión;
            // restringido: solo administradores y operadores.
            $table->string('visibilidad', 20)->default('publico')->after('estado');
            // Política editorial por evento: por defecto la grabación se publica sola.
            $table->boolean('requiere_aprobacion')->default(false)->after('visibilidad');
        });

        Schema::table('transmisiones', function (Blueprint $table) {
            // Identificador público de reproducción, separado de la clave de OBS.
            $table->string('playback_id', 40)->nullable()->unique()->after('stream_key');
            // pendiente: espera revisión (solo si el evento lo requiere); aprobada: visible; retirada: sin acceso público.
            $table->string('publicacion', 20)->default('pendiente')->after('recording_status');
            $table->foreignId('publicacion_revisada_por')->nullable()->after('publicacion')->constrained('users')->nullOnDelete();
            $table->timestamp('publicacion_revisada_en')->nullable()->after('publicacion_revisada_por');
        });

        Schema::table('scheduled_videos', function (Blueprint $table) {
            $table->string('source_sha256', 64)->nullable();
            $table->string('output_sha256', 64)->nullable();
        });

        Schema::table('institutional_ads', function (Blueprint $table) {
            $table->string('source_sha256', 64)->nullable();
            $table->string('output_sha256', 64)->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('action', 80)->index();
            $table->string('subject_type', 80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->json('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id']);
        });

        DB::table('transmisiones')->whereNull('playback_id')->orderBy('id')->select('id')
            ->each(fn ($row) => DB::table('transmisiones')->where('id', $row->id)
                ->update(['playback_id' => Str::lower(Str::random(32))]));
        // Lo que ya estaba publicado no desaparece al actualizar.
        DB::table('transmisiones')->update(['publicacion' => 'aprobada']);
        $all = json_encode(['events', 'stream', 'moderate_chat', 'toggle_chat']);
        DB::table('users')->where('role', 'operador')->whereNull('operator_permissions')
            ->update(['operator_permissions' => $all]);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::table('institutional_ads', fn (Blueprint $table) => $table->dropColumn(['source_sha256', 'output_sha256']));
        Schema::table('scheduled_videos', fn (Blueprint $table) => $table->dropColumn(['source_sha256', 'output_sha256']));
        Schema::table('transmisiones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('publicacion_revisada_por');
            $table->dropUnique(['playback_id']);
            $table->dropColumn(['playback_id', 'publicacion', 'publicacion_revisada_en']);
        });
        Schema::table('eventos', fn (Blueprint $table) => $table->dropColumn(['visibilidad', 'requiere_aprobacion']));
    }
};
