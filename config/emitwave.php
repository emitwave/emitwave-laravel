<?php

return [
    'gateway_url' => env('EMITWAVE_GATEWAY_URL', 'https://api.emitwave.com'),
    'app_id' => env('EMITWAVE_APP_ID'),
    'key' => env('EMITWAVE_KEY'),
    'secret' => env('EMITWAVE_SECRET'),
    'middleware' => ['web', 'auth'],
    'route_prefix' => 'emitwave',
];
