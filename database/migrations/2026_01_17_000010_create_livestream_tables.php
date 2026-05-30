<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_streams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('stream_key')->unique();
            $table->boolean('is_live')->default(false);
            $table->timestamps();

            $table->index(['is_live', 'created_at']);
        });

        Schema::create('stream_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('live_stream_id')->constrained()->cascadeOnDelete();
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->integer('peak_viewers')->default(0);
            $table->decimal('total_coins_collected', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['live_stream_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_sessions');
        Schema::dropIfExists('live_streams');
    }
};
