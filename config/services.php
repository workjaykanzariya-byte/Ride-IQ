<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS', 'app/firebase/firebase_credentials.json'),
    ],

    'google' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'uber' => [
        'enabled' => env('UBER_ENABLED', true),
        'base_url' => env('UBER_BASE_URL', ''),
        'client_id' => env('UBER_CLIENT_ID'),
        'client_secret' => env('UBER_CLIENT_SECRET'),
    ],

    'lyft' => [
        'base_url' => env('LYFT_BASE_URL', ''),
        'client_id' => env('LYFT_CLIENT_ID'),
        'client_secret' => env('LYFT_CLIENT_SECRET'),
    ],

    'ayro' => [
        'base_url' => env('AYRO_BASE_URL', ''),
        'client_id' => env('AYRO_CLIENT_ID'),
        'client_secret' => env('AYRO_CLIENT_SECRET'),
    ],

    'location' => [
        'mock' => env('LOCATION_MOCK', false),
    ],

];
