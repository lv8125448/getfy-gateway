<?php

use App\Support\VapidEnvKeys;

$versionFile = base_path('VERSION');
$version = trim((is_file($versionFile) ? file_get_contents($versionFile) : '') ?: '') ?: env('GETFY_VERSION', '1.0.0');

$cloudModeEnv = filter_var(env('GETFY_CLOUD', false), FILTER_VALIDATE_BOOLEAN);
$cloudModeFolder = is_dir(base_path('cloud'));

return [
    'installed' => is_file(base_path('.env')) && filter_var(env('APP_INSTALLED', false), FILTER_VALIDATE_BOOLEAN),
    'cloud_mode' => $cloudModeEnv || $cloudModeFolder,
    'cloud' => [
        'orch_api_base_url' => rtrim((string) env('ORCH_API_BASE_URL', 'https://orch.getfy.cloud'), '/'),
        'billing_cache_minutes' => (int) env('GETFY_CLOUD_BILLING_CACHE_MINUTES', 10),
        'billing_renew_window_days' => (int) env('GETFY_CLOUD_BILLING_RENEW_WINDOW_DAYS', 7),
    ],
    'auto_migrate' => filter_var(env('APP_AUTO_MIGRATE', false), FILTER_VALIDATE_BOOLEAN),
    'cron_secret' => env('CRON_SECRET', null),
    'version' => $version,
    'update_repository_url' => env('GETFY_UPDATE_REPO', 'https://github.com/getfy-opensource/getfy.git'),
    'update_branch' => env('GETFY_UPDATE_BRANCH', 'main'),
    'updates_enabled' => env('GETFY_UPDATES_ENABLED', true),
    'php_path' => env('GETFY_PHP_PATH', null),
    'pwa' => [
        'vapid_public' => VapidEnvKeys::normalize(env('PWA_VAPID_PUBLIC')),
        'vapid_private' => VapidEnvKeys::normalize(env('PWA_VAPID_PRIVATE')),
    ],
    'app_name' => 'Getfy',
    'theme_primary' => '#00cc00',
    'app_logo' => 'https://cdn.getfy.cloud/logo-white.png',
    'app_logo_dark' => 'https://cdn.getfy.cloud/logo-dark.png',
    'app_logo_icon' => 'https://cdn.getfy.cloud/collapsed-logo.png',
    'app_logo_icon_dark' => 'https://cdn.getfy.cloud/collapsed-logo.png',

    /** White Label plugin (null = default / não aplicado) */
    'login_hero_image' => null,
    'favicon_url' => null,
    'pwa_theme_color' => null,
    'pwa_icon_192' => null,
    'pwa_icon_512' => null,

    /*
    | Segurança operador da plataforma (roadmap): 2FA TOTP obrigatório para platform_admin;
    | allowlist de IP via env; sessão já regenerada no login (/login e /plataforma/login).
    */

    /**
     * URL pública base (HTTPS) para montar postbacks da Spacepag quando APP_URL é local ou HTTP.
     * Ex.: https://api.sualoja.com — o path /webhooks/gateways/spacepag é acrescentado automaticamente.
     */
    'webhook_public_url' => is_string($v = env('GETFY_WEBHOOK_PUBLIC_URL')) && trim($v) !== ''
        ? rtrim(trim($v), '/')
        : null,
];
