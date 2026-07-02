<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignUuid('stream_session_id')->nullable()->after('gift_id')
                ->constrained('stream_sessions')->nullOnDelete();
            $table->index('stream_session_id');
            $table->index(['to_wallet_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['to_wallet_id', 'type', 'created_at']);
            $table->dropIndex(['stream_session_id']);
            $table->dropConstrainedForeignId('stream_session_id');
        });
    }
};
