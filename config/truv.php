<?php

return [
    'base_url' => env('TRUV_BASE_URL', 'https://prod.truv.com/v1'),
    'client_id' => env('TRUV_CLIENT_ID'),
    'access_secret' => env('TRUV_ACCESS_SECRET'),
    'env' => env('TRUV_ENV', 'sandbox'),
    'timeout' => env('TRUV_TIMEOUT', 15),
    'retry_times' => env('TRUV_RETRY_TIMES', 2),
    'retry_sleep_ms' => env('TRUV_RETRY_SLEEP_MS', 200),
];
