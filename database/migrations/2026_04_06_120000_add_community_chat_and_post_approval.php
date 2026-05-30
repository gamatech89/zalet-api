<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Link each board to its group chat conversation
        Schema::table('boards', function (Blueprint $table) {
            $table->uuid('conversation_id')->nullable()->after('is_public');
            $table->foreign('conversation_id')->references('id')->on('conversations')->nullOnDelete();
        });

        // Post approval status for private communities
        // Default 'approved' so existing posts and public-board posts are unaffected
        Schema::table('board_posts', function (Blueprint $table) {
            $table->string('status')->default('approved')->after('is_active');
            $table->index(['board_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('board_posts', function (Blueprint $table) {
            $table->dropIndex(['board_id', 'status']);
            $table->dropColumn('status');
        });

        Schema::table('boards', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropColumn('conversation_id');
        });
    }
};
