<?php

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
        Schema::create('places', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('external_id')->index(); // GeoNames ID or Google Place ID
            $table->string('source')->default('geonames'); // 'geonames', 'google'
            $table->string('type')->default('city'); // 'city', 'country'
            $table->string('country_code', 2)->index(); // 'AT', 'RS'
            $table->jsonb('coordinates')->nullable();
            $table->timestamps();
        });

        Schema::create('place_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('place_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10)->index(); // 'en', 'sr', 'sr-Latn', 'de'
            $table->string('name');
            $table->unique(['place_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('place_translations');
        Schema::dropIfExists('places');
    }
};
