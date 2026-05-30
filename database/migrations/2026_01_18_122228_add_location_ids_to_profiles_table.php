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
        Schema::table('profiles', function (Blueprint $table) {
            $table->foreignUuid('hometown_place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->foreignUuid('current_place_id')->nullable()->constrained('places')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropForeign(['hometown_place_id']);
            $table->dropForeign(['current_place_id']);
            $table->dropColumn(['hometown_place_id', 'current_place_id']);
        });
    }
};
