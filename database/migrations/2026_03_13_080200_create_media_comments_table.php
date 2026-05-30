<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('media_id')->constrained()->cascadeOnDelete();
            $table->uuid('parent_id')->nullable()->index();
            $table->text('body');
            $table->timestamps();

            $table->index(['media_id', 'created_at']);
        });

        // Add self-referencing FK after table creation
        Schema::table('media_comments', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('media_comments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_comments');
    }
};
