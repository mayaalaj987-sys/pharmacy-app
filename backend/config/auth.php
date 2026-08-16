<?php

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Pharmacist;

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

        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
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
            'model' => Pharmacist::class,
        ],

        'employees' => [
            'driver' => 'eloquent',
            'model' => Employee::class,
        ],

        'admins' => [
            'driver' => 'eloquent',
            'model' => Admin::class,
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
