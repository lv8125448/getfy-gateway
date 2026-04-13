<?php

$rates = [
    'brl_eur' => (float) env('EXCHANGE_BRL_EUR', 0.16),
    'brl_usd' => (float) env('EXCHANGE_BRL_USD', 0.18),
];

return [
    'currency_default' => env('PRODUCT_CURRENCY_DEFAULT', 'BRL'),

    'rates' => $rates,

    'currencies' => [
        ['code' => 'BRL', 'symbol' => 'R$', 'label' => 'Real brasileiro', 'rate_to_brl' => 1.0],
        ['code' => 'USD', 'symbol' => 'US$', 'label' => 'Dólar americano', 'rate_to_brl' => $rates['brl_usd']],
        ['code' => 'EUR', 'symbol' => '€', 'label' => 'Euro', 'rate_to_brl' => $rates['brl_eur']],
    ],
];
