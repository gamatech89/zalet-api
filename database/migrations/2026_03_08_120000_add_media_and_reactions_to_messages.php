<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add media columns to messages
        Schema::table('messages', function (Blueprint $table) {
            $table->string('message_type')->default('text')->after('content'); // text, image, file
            $table->string('media_url')->nullable()->after('message_type');
            $table->text('content')->nullable()->change(); // Allow null content for image-only messages
        });

        // Create message reactions table
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 8); // e.g. '❤️', '👍', '😂'
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['message_id', 'user_id', 'emoji']); // One reaction type per user per message
            $table->index(['message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['message_type', 'media_url']);
        });
    }
};