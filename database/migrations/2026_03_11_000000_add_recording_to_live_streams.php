<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->string('recording_url')->nullable()->after('livekit_room_name');
            $table->string('recording_disk')->default('local')->after('recording_url');
            $table->unsignedInteger('recording_duration')->nullable()->after('recording_disk');
            $table->unsignedBigInteger('recording_size')->nullable()->after('recording_duration');
            $table->boolean('has_recording')->default(false)->after('recording_size');
        });
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropColumn(['recording_url', 'recording_disk', 'recording_duration', 'recording_size', 'has_recording']);
        });
    }
};
