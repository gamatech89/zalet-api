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
        Schema::table('gift_catalog', function (Blueprint $table) {
            $table->boolean('is_epic')->default(false)->after('coin_price');
            $table->boolean('is_rare')->default(false)->after('is_epic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gift_catalog', function (Blueprint $table) {
            $table->dropColumn(['is_epic', 'is_rare']);
        });
    }
};
