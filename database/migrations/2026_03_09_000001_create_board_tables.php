<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Community boards — one per city/hub
        Schema::create('boards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // e.g. "Wien", "München", "Chicago"
            $table->string('slug')->unique();
            $table->string('country_code', 2);
            $table->string('city')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('country_code');
            $table->index('slug');
        });

        // Board posts — classifieds within a board
        Schema::create('board_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('board_id');
            $table->uuid('user_id');
            $table->string('title');
            $table->text('body');
            $table->enum('category', ['apartment', 'job', 'roommate', 'ride', 'advice', 'general']);
            $table->enum('type', ['need', 'offer', 'question'])->default('offer');
            $table->jsonb('images')->nullable(); // array of image URLs
            $table->string('location_label')->nullable(); // e.g. "Wien, 10th district"
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();

            $table->foreign('board_id')->references('id')->on('boards')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['board_id', 'category']);
            $table->index(['board_id', 'created_at']);
            $table->index('user_id');
        });

        // Comments on board posts
        Schema::create('board_post_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('post_id');
            $table->uuid('user_id');
            $table->text('body');
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamps();

            $table->foreign('post_id')->references('id')->on('board_posts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['post_id', 'created_at']);
        });

        // Likes on board posts
        Schema::create('board_post_likes', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->uuid('post_id');
            $table->timestamps();

            $table->primary(['user_id', 'post_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('post_id')->references('id')->on('board_posts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_post_likes');
        Schema::dropIfExists('board_post_comments');
        Schema::dropIfExists('board_posts');
        Schema::dropIfExists('boards');
    }
};