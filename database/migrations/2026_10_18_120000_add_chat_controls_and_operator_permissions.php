<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // NULL preserves the existing operator's former access until an admin edits it.
            $table->json('operator_permissions')->nullable();
            $table->boolean('chat_restricted')->default(false);
        });

        Schema::table('eventos', function (Blueprint $table) {
            $table->boolean('chat_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('eventos', fn (Blueprint $table) => $table->dropColumn('chat_enabled'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['operator_permissions', 'chat_restricted']);
        });
    }
};
