<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Corrige REQUEST_URI quando Apache serve via DirectoryIndex (evita 404 na raiz)
$uri = $_SERVER['REQUEST_URI'] ?? '';
if ($uri === '/index.php' || $uri === '') {
    $_SERVER['REQUEST_URI'] = '/';
}

// Instalador: serve diretamente sem carregar Laravel (funciona sem .env)
$uriPath = parse_url($uri, PHP_URL_PATH) ?: $uri;
if (str_starts_with($uriPath, '/install')) {
    $installDir = __DIR__ . '/install';
    if (! is_dir($installDir)) {
        $installDir = __DIR__ . '/.install';
    }
    if (is_dir($installDir)) {
        if (str_ends_with(rtrim($uriPath, '/'), '/install/api') || preg_match('#/install/api\.php$#', $uriPath)) {
            require $installDir . '/api.php';
            exit;
        }
        if (preg_match('#/install/install\.js$#', $uriPath)) {
            $file = $installDir . '/install.js';
            if (file_exists($file)) {
                header('Content-Type: application/javascript');
                readfile($file);
                exit;
            }
        }
        require $installDir . '/index.php';
        exit;
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
