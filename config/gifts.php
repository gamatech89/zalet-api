<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Virtual Gift Catalog
    |--------------------------------------------------------------------------
    |
    | Configuration for virtual gifts that users can send to creators.
    | Each gift has a unique key, display name, credit cost, icon, and animation.
    |
    */

    'rakija' => [
        'name' => 'Rakija',
        'credits' => 5,
        'icon' => '🥃',
        'animation' => 'bounce',
    ],

    'rose' => [
        'name' => 'Ruža',
        'credits' => 10,
        'icon' => '🌹',
        'animation' => 'float',
    ],

    'heart' => [
        'name' => 'Srce',
        'credits' => 25,
        'icon' => '❤️',
        'animation' => 'pulse',
    ],

    'crown' => [
        'name' => 'Kruna',
        'credits' => 100,
        'icon' => '👑',
        'animation' => 'sparkle',
    ],

    'car' => [
        'name' => 'Auto',
        'credits' => 500,
        'icon' => '🚗',
        'animation' => 'drive',
    ],
];
