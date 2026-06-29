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
        Schema::table('stream_sessions', function (Blueprint $table) {
            $table->integer('current_viewers')->default(0)->after('peak_viewers');
        });
    }

    public function down(): void
    {
        Schema::table('stream_sessions', function (Blueprint $table) {
            $table->dropColumn('current_viewers');
        });
    }
};
