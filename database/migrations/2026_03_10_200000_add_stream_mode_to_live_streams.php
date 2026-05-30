<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->string('stream_mode', 20)->default('scena')->after('is_live');
            $table->string('livekit_room_name')->nullable()->after('stream_mode');

            $table->index(['stream_mode', 'is_live']);
        });
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropIndex(['stream_mode', 'is_live']);
            $table->dropColumn(['stream_mode', 'livekit_room_name']);
        });
    }
};
