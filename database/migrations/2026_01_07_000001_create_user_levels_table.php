<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('xp')->default(0);
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('bar_messages_today')->default(0);
            $table->unsignedInteger('bar_reactions_today')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index('level');
            $table->index('xp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_levels');
    }
};
