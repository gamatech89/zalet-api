<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Level Tiers (Činovi)
    |--------------------------------------------------------------------------
    |
    | Define the tier names for each level range. Users will be assigned
    | a tier based on their current level. Easy to customize!
    |
    */
    'tiers' => [
        ['min_level' => 1,   'name' => 'Početnik',      'name_en' => 'Rookie',          'icon' => '🌱'],
        ['min_level' => 5,   'name' => 'Redov',         'name_en' => 'Private',         'icon' => '⭐'],
        ['min_level' => 10,  'name' => 'Desetar',       'name_en' => 'Corporal',        'icon' => '⭐⭐'],
        ['min_level' => 20,  'name' => 'Vodnik',        'name_en' => 'Sergeant',        'icon' => '🎖️'],
        ['min_level' => 30,  'name' => 'Poručnik',      'name_en' => 'Lieutenant',      'icon' => '🎖️🎖️'],
        ['min_level' => 40,  'name' => 'Kapetan',       'name_en' => 'Captain',         'icon' => '🏅'],
        ['min_level' => 50,  'name' => 'Major',         'name_en' => 'Major',           'icon' => '🏅🏅'],
        ['min_level' => 60,  'name' => 'Pukovnik',      'name_en' => 'Colonel',         'icon' => '🎯'],
        ['min_level' => 70,  'name' => 'General',       'name_en' => 'General',         'icon' => '⚔️'],
        ['min_level' => 80,  'name' => 'Vojvoda',       'name_en' => 'Duke',            'icon' => '🛡️'],
        ['min_level' => 90,  'name' => 'Car',           'name_en' => 'Emperor',         'icon' => '👑'],
        ['min_level' => 100, 'name' => 'Kralj Balkana', 'name_en' => 'King of Balkans', 'icon' => '👑🔥'],
    ],

    /*
    |--------------------------------------------------------------------------
    | XP Formula
    |--------------------------------------------------------------------------
    |
    | XP required for each level. Can be flat or use a formula.
    | Formula: base_xp * (level ^ multiplier)
    |
    */
    'xp_formula' => [
        'type' => 'exponential', // 'flat' or 'exponential'
        'base_xp' => 100,
        'multiplier' => 1.5, // Only used for exponential
    ],

    /*
    |--------------------------------------------------------------------------
    | Bar Creation Perks
    |--------------------------------------------------------------------------
    |
    | Define what bar creation privileges users get at each level.
    |
    */
    'bar_perks' => [
        5  => ['max_bars' => 1,  'max_members' => 50],
        10 => ['max_bars' => 2,  'max_members' => 100],
        20 => ['max_bars' => 3,  'max_members' => 250],
        30 => ['max_bars' => 4,  'max_members' => 350],
        40 => ['max_bars' => 5,  'max_members' => 500],
        50 => ['max_bars' => 7,  'max_members' => 750],
        70 => ['max_bars' => 10, 'max_members' => 1000],
        90 => ['max_bars' => 15, 'max_members' => 2000],
    ],

    /*
    |--------------------------------------------------------------------------
    | XP Rewards
    |--------------------------------------------------------------------------
    |
    | How much XP users earn for various activities.
    |
    */
    'xp_rewards' => [
        'watch_stream_per_minute' => 1,
        'stream_per_minute' => 5,
        'receive_gift_multiplier' => 2,  // gift_value * multiplier
        'send_gift_multiplier' => 1,     // gift_value * multiplier
        'bar_message' => 1,
        'bar_message_daily_cap' => 50,
        'bar_reaction' => 1,
        'bar_reaction_daily_cap' => 20,
        'create_bar_event' => 10,
        'host_bar_stream' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Level Cap
    |--------------------------------------------------------------------------
    */
    'max_level' => 100,
];
