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
        Schema::create('chat_rooms', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('type', 20)->default('public_kafana');
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->integer('max_participants')->default(500);
            $table->boolean('is_active')->default(true);
            $table->jsonb('meta')->default('{}');
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index('location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_rooms');
    }
};
