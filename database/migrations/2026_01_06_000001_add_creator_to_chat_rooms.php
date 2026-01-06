<?php

declare(strict_types=1);

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
        Schema::table('chat_rooms', function (Blueprint $table): void {
            $table->foreignId('creator_id')->nullable()->after('location_id')->constrained('users')->nullOnDelete();
            $table->string('description', 500)->nullable()->after('name');
            $table->index('creator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table): void {
            $table->dropForeign(['creator_id']);
            $table->dropColumn(['creator_id', 'description']);
        });
    }
};
