<?php

/**
 * Subscription plan limits per tier level.
 *
 * Level 0 = Free (no subscription)
 * Level 1 = Premium
 * Level 2 = VIP
 *
 * null = unlimited
 */

return [

    // Free — level 0
    0 => [
        'max_moments'                   => 10,
        'max_moment_duration_seconds'   => 30,
        'max_groups'                    => 3,
        'max_community_posts_per_month' => 0,      // cannot post for free
        'community_post_coin_cost'      => 5,       // but can pay ZC per post
        'can_watch_premium'             => false,
        'can_create_community'          => false,
        'monthly_free_coins'            => 0,
    ],

    // Premium — level 1
    1 => [
        'max_moments'                   => 50,
        'max_moment_duration_seconds'   => 60,
        'max_groups'                    => null,     // unlimited
        'max_community_posts_per_month' => 10,
        'community_post_coin_cost'      => 0,
        'can_watch_premium'             => true,
        'can_create_community'          => false,
        'monthly_free_coins'            => 0,
    ],

    // VIP — level 2
    2 => [
        'max_moments'                   => 100,
        'max_moment_duration_seconds'   => 60,
        'max_groups'                    => null,     // unlimited
        'max_community_posts_per_month' => null,     // unlimited
        'community_post_coin_cost'      => 0,
        'can_watch_premium'             => true,
        'can_create_community'          => true,     // admin must approve
        'monthly_free_coins'            => 500,
    ],

];
