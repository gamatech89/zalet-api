<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stream_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('category', 50)->default('other');
            $table->string('thumbnail_url')->nullable();
            $table->string('room_name', 100)->nullable()->unique();
            $table->boolean('is_public')->default(true);
            $table->enum('status', ['pending', 'live', 'ended', 'cancelled'])->default('pending');
            $table->integer('viewer_count')->default(0);
            $table->integer('peak_viewers')->default(0);
            $table->integer('total_viewers')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'is_public']);
            $table->index(['user_id', 'status']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_sessions');
    }
};
