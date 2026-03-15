<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Credentials
    |--------------------------------------------------------------------------
    |
    | Path to your Firebase service account JSON file or environment variable
    | containing the JSON content.
    |
    */
    'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-credentials.json')),

    /*
    |--------------------------------------------------------------------------
    | Token Management
    |--------------------------------------------------------------------------
    |
    | Configure how FCM tokens are managed and cleaned up.
    |
    */
    'tokens' => [
        // Automatically invalidate tokens after this many days of inactivity
        'expire_inactive_days' => 30,

        // Maximum number of tokens per notifiable
        'max_per_notifiable' => 10,

        // Whether to automatically clean expired tokens via scheduled command
        'auto_clean' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging behavior for FCM notifications.
    |
    */
    'logging' => [
        'enabled' => env('FCM_LOGGING_ENABLED', true),
        'channel' => env('FCM_LOG_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Channel Name
    |--------------------------------------------------------------------------
    |
    | The name of the channel to use in notifications via() method.
    |
    */
    'channel_name' => 'fcm',

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Whether to queue FCM notifications by default.
    |
    */
    'queue' => [
        'enabled' => env('FCM_QUEUE_ENABLED', true),
        'connection' => env('FCM_QUEUE_CONNECTION', 'redis'),
        'queue' => env('FCM_QUEUE_NAME', 'default'),
    ],
];