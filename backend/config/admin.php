<?php

return [
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'ADMIN_ALLOWED_ORIGINS',
            'http://localhost:3000,http://localhost:5173,http://127.0.0.1:3000,http://127.0.0.1:5173'
        ))
    ))),

    'audit' => [
        'capture_ip' => env('ADMIN_AUDIT_CAPTURE_IP', true),
        'capture_user_agent' => env('ADMIN_AUDIT_CAPTURE_USER_AGENT', true),
        'user_agent_max_length' => 512,
        'reason_max_length' => 500,
        'operational_retention_days' => (int) env('ADMIN_AUDIT_RETENTION_DAYS', 90),
    ],
];
