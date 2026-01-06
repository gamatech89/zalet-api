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
        Schema::create('duel_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->string('event_type', 30);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('payload')->default('{}');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['live_session_id', 'created_at']);
            $table->index(['event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duel_events');
    }
};
