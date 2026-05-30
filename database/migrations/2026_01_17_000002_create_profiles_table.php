<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('bio')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('hometown_city')->nullable();
            $table->string('hometown_country')->nullable();
            $table->string('current_city')->nullable();
            $table->string('current_country')->nullable();
            $table->jsonb('coordinates')->nullable(); // {lat, lng}
            $table->timestamps();

            $table->index(['hometown_city', 'hometown_country']);
            $table->index(['current_city', 'current_country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
