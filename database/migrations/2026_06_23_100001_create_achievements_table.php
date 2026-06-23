<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('event_type');
            $table->json('aggregation');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('achievement_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('achievement_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('level');
            $table->unsignedInteger('threshold');
            $table->string('icon')->nullable();
            $table->json('reward')->nullable();
            $table->timestamps();

            $table->unique(['achievement_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievement_tiers');
        Schema::dropIfExists('achievements');
    }
};
