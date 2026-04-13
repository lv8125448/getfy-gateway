<?php

use App\Gateways\GatewayRegistry;

require_once __DIR__ . '/src/ExampleGatewayDriver.php';
require_once __DIR__ . '/src/ExampleGatewayWebhookHandler.php';

return function ($app, \Illuminate\Contracts\Events\Dispatcher $events): void {
    GatewayRegistry::register([
        'slug' => 'example-gateway',
        'name' => 'Example Gateway',
        'image' => 'plugin:example-gateway/logo.png',
        'methods' => ['pix', 'card', 'boleto'],
        'scope' => 'national',
        'country' => 'br',
        'country_name' => 'Brasil',
        'country_flag' => 'brasil.png',
        'signup_url' => 'https://example.com',
        'driver' => \Plugins\ExampleGateway\ExampleGatewayDriver::class,
        'credential_keys' => [
            ['key' => 'api_key', 'label' => 'API Key (exemplo)', 'type' => 'password'],
            ['key' => 'sandbox', 'label' => 'Sandbox', 'type' => 'boolean'],
        ],
        'webhook_handler' => \Plugins\ExampleGateway\ExampleGatewayWebhookHandler::class,
    ]);
};
