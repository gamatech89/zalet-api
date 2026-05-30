<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Tags table
        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique(); // e.g. "music", "sports"
            $table->string('label'); // Display label e.g. "Muzika", "Sport"
            $table->string('color')->nullable(); // Optional hex color for badge
            $table->timestamps();
        });

        // Pivot table: media <-> tags (many-to-many)
        Schema::create('media_tag', function (Blueprint $table) {
            $table->uuid('media_id');
            $table->uuid('tag_id');
            $table->primary(['media_id', 'tag_id']);

            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_tag');
        Schema::dropIfExists('tags');
    }
};