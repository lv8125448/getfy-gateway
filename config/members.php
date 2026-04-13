<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Member area URL strategy
    |--------------------------------------------------------------------------
    | Path: /m/{slug}
    | Subdomain: {slug}.members.{subdomain_base}
    | Custom: domain stored per product in member_area_domains
    */
    'subdomain_enabled' => env('MEMBER_AREA_SUBDOMAIN_ENABLED', false),
    'subdomain_base' => env('MEMBER_AREA_SUBDOMAIN_BASE', 'members.' . parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),

    /*
    |--------------------------------------------------------------------------
    | Push notifications (PWA)
    |--------------------------------------------------------------------------
    */
    'push' => [
        'vapid_public' => env('PWA_VAPID_PUBLIC', ''),
        'vapid_private' => env('PWA_VAPID_PRIVATE', ''),
    ],
];
