<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('slug')->unique();
            $table->text('descripcion')->nullable();
            $table->string('tipo_evento'); // Graduaciones, Conferencias, etc.
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->nullable();
            $table->string('lugar')->nullable();
            $table->foreignId('organizador_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('facultad_id')->nullable()->constrained('facultades')->nullOnDelete();
            $table->foreignId('carrera_id')->nullable()->constrained('carreras')->nullOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->onDelete('cascade');
            
            $table->string('imagen_principal')->nullable();
            $table->string('banner')->nullable();
            $table->string('miniatura')->nullable();
            
            $table->enum('estado', ['programado', 'en_vivo', 'finalizado', 'cancelado'])->default('programado');
            $table->string('url_transmision')->nullable();
            $table->integer('duracion_minutos')->nullable();
            $table->foreignId('responsable_streaming_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'fecha_inicio']);
            $table->index('categoria_id');
        });

        Schema::create('transmisiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->onDelete('cascade');
            $table->string('stream_key')->unique();
            $table->string('rtmp_server')->nullable();
            $table->string('hls_url')->nullable();
            $table->integer('espectadores_activos')->default(0);
            $table->boolean('activo')->default(false);
            $table->timestamp('iniciado_en')->nullable();
            $table->timestamp('finalizado_en')->nullable();
            $table->timestamps();

            $table->index('evento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transmisiones');
        Schema::dropIfExists('eventos');
    }
};