<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscription_plans')->where('slug', 'premium')->update([
            'limits' => json_encode([
                'max_moments' => 10,
                'max_moment_duration_seconds' => 60,
                'max_groups' => null,
                'can_create_group' => false,
                'max_community_posts_per_month' => 10,
                'community_post_coin_cost' => 0,
                'can_watch_premium' => true,
                'can_create_community' => false,
                'monthly_free_coins' => 0,
            ]),
        ]);

        DB::table('subscription_plans')->where('slug', 'vip')->update([
            'limits' => json_encode([
                'max_moments' => null,
                'max_moment_duration_seconds' => 60,
                'max_groups' => null,
                'can_create_group' => true,
                'max_community_posts_per_month' => null,
                'community_post_coin_cost' => 0,
                'can_watch_premium' => true,
                'can_create_community' => true,
                'monthly_free_coins' => 1500,
            ]),
        ]);
    }

    public function down(): void {}
};
