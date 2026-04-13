<?php

namespace App\Http\Middleware;

use App\Models\BrandingSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ApplyBrandingConfig
{
    private const CONFIG_KEYS = [
        'app_name' => 'getfy.app_name',
        'theme_primary' => 'getfy.theme_primary',
        'pwa_theme_color' => 'getfy.pwa_theme_color',
        'app_logo' => 'getfy.app_logo',
        'app_logo_dark' => 'getfy.app_logo_dark',
        'app_logo_icon' => 'getfy.app_logo_icon',
        'app_logo_icon_dark' => 'getfy.app_logo_icon_dark',
        'login_hero_image' => 'getfy.login_hero_image',
        'favicon_url' => 'getfy.favicon_url',
        'pwa_icon_192' => 'getfy.pwa_icon_192',
        'pwa_icon_512' => 'getfy.pwa_icon_512',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (! Schema::hasTable('branding_settings')) {
                return $next($request);
            }
        } catch (\Throwable) {
            return $next($request);
        }

        $data = $this->effectiveData($request);
        $merge = [];
        foreach (self::CONFIG_KEYS as $jsonKey => $configKey) {
            $v = $data[$jsonKey] ?? null;
            if (is_string($v) && $v !== '') {
                $merge[$configKey] = $v;
            }
        }
        if ($merge !== []) {
            config($merge);
        }

        return $next($request);
    }

    public static function mergeLayers(array $global, array $tenant): array
    {
        $keys = array_unique(array_merge(array_keys($global), array_keys($tenant)));
        $out = [];
        foreach ($keys as $k) {
            $v = $tenant[$k] ?? null;
            if (is_string($v) && $v !== '') {
                $out[$k] = $v;
            } elseif (isset($global[$k]) && is_string($global[$k]) && $global[$k] !== '') {
                $out[$k] = $global[$k];
            }
        }

        return $out;
    }

    private function effectiveData(Request $request): array
    {
        $global = BrandingSetting::query()->whereNull('tenant_id')->first();
        $globalData = is_array($global?->data) ? $global->data : [];
        $user = $request->user();
        if ($user === null) {
            return $globalData;
        }

        $tenant = BrandingSetting::query()->where('tenant_id', $user->tenant_id)->first();
        $tenantData = is_array($tenant?->data) ? $tenant->data : [];

        return self::mergeLayers($globalData, $tenantData);
    }
}
