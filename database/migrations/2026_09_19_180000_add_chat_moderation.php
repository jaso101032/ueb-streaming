<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['evento_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['evento_id', 'id']);
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropSoftDeletes();
        });
    }
};
