<?php

return [
    'routing' => [
        'mode' => env('DEV_MODE', 'path'),
        'prefix' => 'dev',
    ],

    'guard' => 'web',

    'navigation' => [
        'route' => 'dev.dashboard',
        'icon'  => 'heroicon-o-code-bracket',
        'order' => 90,
    ],

    'sidebar' => [
        [
            'group' => 'Allgemein',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'dev.dashboard',
                    'icon'  => 'heroicon-o-home',
                ],
            ],
        ],
    ],
];
