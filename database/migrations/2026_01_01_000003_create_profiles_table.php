<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('username', 50)->unique();
            $table->string('display_name', 100)->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->foreignId('origin_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('current_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->boolean('is_private')->default(false);
            $table->timestamps();

            $table->index('origin_location_id');
            $table->index('current_location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
