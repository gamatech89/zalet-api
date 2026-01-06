<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // 'video', 'short_clip', 'image'
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->text('source_url');
            $table->string('provider', 30)->nullable(); // 'youtube', 'vimeo', 'mux', 'local'
            $table->string('provider_id', 100)->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->jsonb('meta')->default('{}');
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'is_published', 'published_at'], 'idx_posts_user_feed');
            $table->index(['type', 'is_published'], 'idx_posts_type');
            $table->index(['provider', 'provider_id'], 'idx_posts_provider');
            $table->index(['is_published', 'published_at'], 'idx_posts_feed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
