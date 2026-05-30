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
        Schema::table('live_streams', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('is_live');
            $table->boolean('chat_enabled')->default(true)->after('scheduled_at');
            // JSON array of {description, target_coins, current_coins}
            $table->json('goals')->nullable()->after('chat_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropColumn(['scheduled_at', 'chat_enabled', 'goals']);
        });
    }
};
