<?php

return [
    'paths' => ['api/admin/*'],
    'allowed_methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => config('admin.allowed_origins', []),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Content-Type', 'Origin', 'X-Requested-With', 'X-XSRF-TOKEN', 'X-Request-ID'],
    'exposed_headers' => ['X-Request-ID'],
    'max_age' => 600,
    'supports_credentials' => true,
];
