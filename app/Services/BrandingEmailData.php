<?php

namespace App\Services;

use App\Http\Middleware\ApplyBrandingConfig;
use App\Models\BrandingSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Dados de marca para templates de e-mail (lê sempre da base; URLs absolutas para imagens).
 */
class BrandingEmailData
{
    /**
     * @return array{app_name: string, theme_primary: string, logo_url: ?string}
     */
    public static function forTenant(?int $tenantId): array
    {
        try {
            if (! Schema::hasTable('branding_settings')) {
                return self::fallback();
            }
        } catch (\Throwable) {
            return self::fallback();
        }

        $global = BrandingSetting::query()->whereNull('tenant_id')->first();
        $globalData = is_array($global?->data) ? $global->data : [];

        if ($tenantId === null) {
            $merged = $globalData;
        } else {
            $tenant = BrandingSetting::query()->where('tenant_id', $tenantId)->first();
            $tenantData = is_array($tenant?->data) ? $tenant->data : [];
            $merged = ApplyBrandingConfig::mergeLayers($globalData, $tenantData);
        }

        return self::normalize($merged);
    }

    /**
     * @param  array<string, mixed>  $merged
     * @return array{app_name: string, theme_primary: string, logo_url: ?string}
     */
    private static function normalize(array $merged): array
    {
        $appName = $merged['app_name'] ?? null;
        if (! is_string($appName) || $appName === '') {
            $appName = (string) config('app.name', 'Getfy');
        }

        $primary = $merged['theme_primary'] ?? null;
        if (! is_string($primary) || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $primary)) {
            $primary = '#4f46e5';
        }

        $logo = $merged['app_logo'] ?? '';
        if (! is_string($logo)) {
            $logo = '';
        }

        return [
            'app_name' => $appName,
            'theme_primary' => $primary,
            'logo_url' => self::absoluteAssetUrl($logo),
        ];
    }

    /**
     * @return array{app_name: string, theme_primary: string, logo_url: ?string}
     */
    private static function fallback(): array
    {
        return [
            'app_name' => (string) config('app.name', 'Getfy'),
            'theme_primary' => '#4f46e5',
            'logo_url' => null,
        ];
    }

    private static function absoluteAssetUrl(string $url): ?string
    {
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $base = rtrim((string) config('app.url'), '/');

        return $base.'/'.ltrim($url, '/');
    }
}
