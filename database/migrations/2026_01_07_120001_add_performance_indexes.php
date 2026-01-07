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
        // Bar members - composite indexes for membership checks
        Schema::table('bar_members', function (Blueprint $table) {
            $table->index(['bar_id', 'user_id'], 'bar_members_bar_user_idx');
            $table->index(['user_id', 'role'], 'bar_members_user_role_idx');
        });

        // Bar messages - composite index for pagination queries
        Schema::table('bar_messages', function (Blueprint $table) {
            $table->index(['bar_id', 'created_at'], 'bar_messages_bar_created_idx');
            $table->index(['bar_id', 'id'], 'bar_messages_bar_id_idx');
        });

        // Stream sessions - for live stream queries
        Schema::table('stream_sessions', function (Blueprint $table) {
            $table->index(['status', 'is_public'], 'stream_sessions_status_public_idx');
            $table->index(['user_id', 'status'], 'stream_sessions_user_status_idx');
        });

        // Live sessions - for lobby and duel queries
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'live_sessions_status_created_idx');
            $table->index(['host_id', 'status'], 'live_sessions_host_status_idx');
        });

        // Notifications - for user notification queries
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read_at'], 'notifications_user_read_idx');
            $table->index(['user_id', 'created_at'], 'notifications_user_created_idx');
        });

        // Bar message reactions - for reaction queries
        Schema::table('bar_message_reactions', function (Blueprint $table) {
            $table->index(['message_id', 'emoji'], 'bar_reactions_message_emoji_idx');
            $table->index(['message_id', 'user_id'], 'bar_reactions_message_user_idx');
        });

        // Bar events - for event queries
        Schema::table('bar_events', function (Blueprint $table) {
            $table->index(['bar_id', 'status'], 'bar_events_bar_status_idx');
            $table->index(['status', 'scheduled_at'], 'bar_events_status_scheduled_idx');
        });

        // Chat rooms - for room listing
        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->index(['type', 'is_active'], 'chat_rooms_type_active_idx');
        });

        // Messages - for conversation queries
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'created_at'], 'messages_conversation_created_idx');
            $table->index(['chat_room_id', 'created_at'], 'messages_room_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bar_members', function (Blueprint $table) {
            $table->dropIndex('bar_members_bar_user_idx');
            $table->dropIndex('bar_members_user_role_idx');
        });

        Schema::table('bar_messages', function (Blueprint $table) {
            $table->dropIndex('bar_messages_bar_created_idx');
            $table->dropIndex('bar_messages_bar_id_idx');
        });

        Schema::table('stream_sessions', function (Blueprint $table) {
            $table->dropIndex('stream_sessions_status_public_idx');
            $table->dropIndex('stream_sessions_user_status_idx');
        });

        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropIndex('live_sessions_status_created_idx');
            $table->dropIndex('live_sessions_host_status_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_read_idx');
            $table->dropIndex('notifications_user_created_idx');
        });

        Schema::table('bar_message_reactions', function (Blueprint $table) {
            $table->dropIndex('bar_reactions_message_emoji_idx');
            $table->dropIndex('bar_reactions_message_user_idx');
        });

        Schema::table('bar_events', function (Blueprint $table) {
            $table->dropIndex('bar_events_bar_status_idx');
            $table->dropIndex('bar_events_status_scheduled_idx');
        });

        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->dropIndex('chat_rooms_type_active_idx');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conversation_created_idx');
            $table->dropIndex('messages_room_created_idx');
        });
    }
};
