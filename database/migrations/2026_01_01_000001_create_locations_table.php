<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('city', 100);
            $table->string('country', 100);
            $table->char('country_code', 2);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['city', 'country_code']);
            $table->index('country_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
