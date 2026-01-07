<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bar_id')->constrained()->onDelete('cascade');
            $table->foreignId('host_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->timestamp('scheduled_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('stream_session_id')->nullable();
            $table->enum('status', ['scheduled', 'live', 'ended', 'cancelled'])->default('scheduled');
            $table->timestamps();

            $table->index(['bar_id', 'status']);
            $table->index(['bar_id', 'scheduled_at']);
            $table->index('host_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bar_events');
    }
};
