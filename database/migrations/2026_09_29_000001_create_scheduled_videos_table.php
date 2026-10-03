<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('scheduled_videos', function (Blueprint $table) {
        $table->id(); $table->foreignId('evento_id')->unique()->constrained('eventos')->cascadeOnDelete();
        $table->string('path'); $table->unsignedInteger('duration'); $table->timestamps();
    }); }
    public function down(): void { Schema::dropIfExists('scheduled_videos'); }
};
