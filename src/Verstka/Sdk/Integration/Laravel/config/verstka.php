<?php

declare(strict_types=1);

return [
    'api_key' => env('VERSTKA_API_KEY', ''),
    'api_secret' => env('VERSTKA_API_SECRET', ''),
    'callback_url' => env('VERSTKA_CALLBACK_URL', ''),
    'api_url' => env('VERSTKA_API_URL', 'https://api.r2.verstka.org/integration'),
    'basic_auth_user' => env('VERSTKA_BASIC_AUTH_USER'),
    'basic_auth_password' => env('VERSTKA_BASIC_AUTH_PASSWORD'),
    'max_content_size' => (int) env('VERSTKA_MAX_CONTENT_SIZE', 104857600),
    'request_timeout' => (float) env('VERSTKA_REQUEST_TIMEOUT', 60),
    'download_timeout' => (float) env('VERSTKA_DOWNLOAD_TIMEOUT', 120),
    'debug' => (bool) env('VERSTKA_DEBUG', false),
    'callback_route_prefix' => env('VERSTKA_CALLBACK_ROUTE_PREFIX', '/verstka'),
];
