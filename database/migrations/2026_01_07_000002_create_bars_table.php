<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bars', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->boolean('is_public')->default(true);
            $table->string('password')->nullable(); // hashed if private with password
            $table->unsignedInteger('member_limit')->default(50);
            $table->unsignedInteger('member_count')->default(1); // owner counts as 1
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_public');
            $table->index('is_active');
            $table->index('owner_id');
            $table->index('member_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bars');
    }
};
