<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_achievements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('achievement_id')->constrained()->cascadeOnDelete();
            $table->decimal('progress', 15, 2)->default(0);
            $table->timestamp('earned_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'achievement_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
    }
};