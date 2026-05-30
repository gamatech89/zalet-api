<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['moment', 'long_form', 'embed']);
            $table->enum('provider', ['native', 'youtube', 'vimeo', 'dailymotion'])->default('native');
            $table->string('url');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->bigInteger('size_bytes')->default(0);
            $table->boolean('is_ppv')->default(false);
            $table->decimal('price_coins', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
