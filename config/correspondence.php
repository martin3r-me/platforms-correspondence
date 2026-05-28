<?php

return [
    'routing' => [
        'mode' => env('CORRESPONDENCE_MODE', 'path'),
        'prefix' => 'correspondence',
    ],
    'guard' => 'web',

    'navigation' => [
        'route' => 'correspondence.dashboard',
        'icon'  => 'heroicon-o-envelope',
        'order' => 35,
    ],

    'sidebar' => [
        [
            'group' => 'Allgemein',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'correspondence.dashboard',
                    'icon'  => 'heroicon-o-home',
                ],
                [
                    'label' => 'Posteingang',
                    'route' => 'correspondence.inbox',
                    'icon'  => 'heroicon-o-inbox',
                ],
            ],
        ],
    ],
];
