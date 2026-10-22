<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_watermarks', function (Blueprint $table) {
            $table->string('position', 20)->default('top-right')->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('player_watermarks', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
