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
        Schema::create('live_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('chat_room_id')->nullable()->constrained('chat_rooms')->nullOnDelete();
            $table->string('status', 20)->default('waiting');
            $table->bigInteger('host_score')->default(0);
            $table->bigInteger('guest_score')->default(0);
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->jsonb('meta')->default('{}');
            $table->timestamps();

            $table->index(['status']);
            $table->index(['host_id', 'status']);
            $table->index(['guest_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_sessions');
    }
};
