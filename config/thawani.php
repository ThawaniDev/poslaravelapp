<?php

return [
    'marketplace_url' => env('THAWANI_MARKETPLACE_URL'),
    'api_key' => env('THAWANI_API_KEY'),
    'api_secret' => env('THAWANI_API_SECRET'),
    'webhook_secret' => env('THAWANI_WEBHOOK_SECRET'),

    // Sync settings
    'sync_batch_size' => env('THAWANI_SYNC_BATCH_SIZE', 50),
    'sync_interval_minutes' => env('THAWANI_SYNC_INTERVAL', 5),
    'queue_max_attempts' => env('THAWANI_QUEUE_MAX_ATTEMPTS', 3),
    'api_timeout' => env('THAWANI_API_TIMEOUT', 30),
];
