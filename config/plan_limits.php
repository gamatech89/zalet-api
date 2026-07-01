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
        'max_moments'                   => 0,       // cannot post moments
        'max_moment_duration_seconds'   => 30,
        'max_groups'                    => 0,       // no group chat access
        'can_create_group'              => false,
        'max_community_posts_per_month' => 0,       // no community access
        'community_post_coin_cost'      => 0,
        'can_watch_premium'             => false,
        'can_create_community'          => false,
        'monthly_free_coins'            => 0,
    ],

    // Premium — level 1
    1 => [
        'max_moments'                   => 10,      // 10 moments/month
        'max_moment_duration_seconds'   => 60,
        'max_groups'                    => null,    // unlimited group joining
        'can_create_group'              => false,   // cannot create groups
        'max_community_posts_per_month' => 10,      // 10 community posts/month
        'community_post_coin_cost'      => 0,
        'can_watch_premium'             => true,
        'can_create_community'          => false,
        'monthly_free_coins'            => 0,
    ],

    // VIP — level 2
    2 => [
        'max_moments'                   => null,    // unlimited moments
        'max_moment_duration_seconds'   => 60,
        'max_groups'                    => null,    // unlimited
        'can_create_group'              => true,    // can create group chats
        'max_community_posts_per_month' => null,    // unlimited
        'community_post_coin_cost'      => 0,
        'can_watch_premium'             => true,
        'can_create_community'          => true,
        'monthly_free_coins'            => 1500,
    ],

];
