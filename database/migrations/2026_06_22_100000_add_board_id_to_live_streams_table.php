<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->foreignUuid('board_id')->nullable()->constrained('boards')->nullOnDelete()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropForeign(['board_id']);
            $table->dropColumn('board_id');
        });
    }
};
