<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('facultades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('slug')->unique();
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('carreras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facultad_id')->constrained('facultades')->onDelete('cascade');
            $table->string('nombre');
            $table->string('slug');
            $table->timestamps();
            
            $table->index(['facultad_id', 'id']);
        });

        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facultad_id')->constrained('facultades')->onDelete('cascade');
            $table->string('nombre');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamentos');
        Schema::dropIfExists('carreras');
        Schema::dropIfExists('facultades');
    }
};
