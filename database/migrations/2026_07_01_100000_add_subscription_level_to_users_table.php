<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('subscription_level')->default(0)->after('is_legacy_founder');
        });

        // Sync existing users based on their current active subscriptions (PostgreSQL syntax)
        DB::statement("
            UPDATE users
            SET subscription_level = sp.level
            FROM subscriptions s
            JOIN subscription_plans sp ON sp.id = s.subscription_plan_id
            WHERE s.user_id = users.id
              AND s.status IN ('active', 'cancelled')
              AND s.ends_at > NOW()
              AND sp.level > 0
        ");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('subscription_level');
        });
    }
};
