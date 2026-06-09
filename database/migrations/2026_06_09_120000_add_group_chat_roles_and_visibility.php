<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_user', function (Blueprint $table) {
            $table->string('role', 20)->default('member')->after('last_read_at');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('is_group');
            $table->string('invite_code', 16)->unique()->nullable()->after('is_public');
        });

        Schema::create('conversation_bans', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id');
            $table->uuid('user_id');
            $table->uuid('banned_by');
            $table->string('reason', 255)->nullable();
            $table->timestamp('banned_at');
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('banned_by')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['conversation_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_bans');

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['invite_code']);
            $table->dropColumn(['is_public', 'invite_code']);
        });

        Schema::table('conversation_user', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
