<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LiveKit Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for LiveKit WebRTC streaming server.
    |
    */

    'api_key' => env('LIVEKIT_API_KEY', 'devkey'),
    'api_secret' => env('LIVEKIT_API_SECRET', 'devsecret123456789012345678901234'),
    'host' => env('LIVEKIT_HOST', 'http://localhost:7880'),
    
    // WebSocket URL for clients (different from HTTP API)
    'ws_url' => env('LIVEKIT_WS_URL', 'ws://localhost:7880'),

    // Token expiration (in seconds)
    'token_ttl' => env('LIVEKIT_TOKEN_TTL', 86400), // 24 hours

    // Default video settings
    'video' => [
        'enabled' => true,
        'width' => 1280,
        'height' => 720,
        'frame_rate' => 30,
    ],

    // Default audio settings
    'audio' => [
        'enabled' => true,
        'echo_cancellation' => true,
        'noise_suppression' => true,
    ],
];
