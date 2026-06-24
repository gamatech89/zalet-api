<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_achievement_tiers', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('achievement_tier_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('progress')->default(0);
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();

            $table->primary(['user_id', 'achievement_tier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievement_tiers');
    }
};
