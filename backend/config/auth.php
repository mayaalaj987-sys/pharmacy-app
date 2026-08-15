<?php

return [

    'defaults' => [
        'guard' => 'pharmacist',
        'passwords' => 'pharmacists',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'pharmacists',
        ],

        'pharmacist' => [
            'driver' => 'sanctum',
            'provider' => 'pharmacists',
        ],

        'employee' => [
            'driver' => 'sanctum',
            'provider' => 'employees',
        ],
    ],

    'providers' => [
        'pharmacists' => [
            'driver' => 'eloquent',
            'model' => App\Models\Pharmacist::class,
        ],

        'employees' => [
            'driver' => 'eloquent',
            'model' => App\Models\Employee::class,
        ],
    ],

    'passwords' => [
        'pharmacists' => [
            'provider' => 'pharmacists',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'employees' => [
            'provider' => 'employees',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
