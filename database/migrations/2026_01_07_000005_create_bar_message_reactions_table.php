<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bar_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('bar_messages')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('emoji', 32); // emoji character or shortcode
            $table->timestamps();

            $table->unique(['message_id', 'user_id', 'emoji']);
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bar_message_reactions');
    }
};
