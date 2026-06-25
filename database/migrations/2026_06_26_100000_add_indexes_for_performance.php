<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // is_active is used in admin filters and auth middleware
        Schema::table('users', function (Blueprint $table) {
            $table->index('is_active');
        });

        // pinned_message_id FK exists but PostgreSQL doesn't auto-index FK columns
        Schema::table('conversations', function (Blueprint $table) {
            $table->index('pinned_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['pinned_message_id']);
        });
    }
};
