<?php

namespace App\Http\Controllers;

use App\Models\BrandingSetting;
use App\Models\PanelPushSubscription;
use App\Services\MemberAreaResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PanelPwaController extends Controller
{
    public function manifest(Request $request): JsonResponse
    {
        $resolved = app(MemberAreaResolver::class)->resolve($request);
        if ($resolved && in_array($resolved['access_type'], ['subdomain', 'custom'], true)) {
            $request->attributes->set('member_area_product', $resolved['product']);
            $request->attributes->set('member_area_access_type', $resolved['access_type']);
            $request->attributes->set('member_area_slug', $resolved['slug']);

            return app()->call(\App\Http\Controllers\MemberAreaAppController::class.'@manifest', [
                'request' => $request,
                'slug' => $resolved['slug'],
            ]);
        }

        $appName = config('getfy.app_name', 'Getfy');
        $themeColor = config('getfy.pwa_theme_color');
        $themeColor = ($themeColor !== null && $themeColor !== '') ? (string) $themeColor : (string) config('getfy.theme_primary', '#0ea5e9');

        $brandingVersion = $this->brandingVersionForRequest($request);

        $icons = [];
        $addIconVariants = function (string $src, string $sizes) use (&$icons, $brandingVersion): void {
            $src = $this->withVersion($src, $brandingVersion);
            $icons[] = ['src' => $src, 'sizes' => $sizes, 'type' => 'image/png', 'purpose' => 'any'];
            $icons[] = ['src' => $src, 'sizes' => $sizes, 'type' => 'image/png', 'purpose' => 'maskable'];
        };

        $pwa192 = is_string($v = config('getfy.pwa_icon_192')) ? trim($v) : '';
        $pwa512 = is_string($v = config('getfy.pwa_icon_512')) ? trim($v) : '';
        $has192 = $pwa192 !== '';
        $has512 = $pwa512 !== '';

        if ($has192 || $has512) {
            if ($has192 && $has512) {
                $addIconVariants($pwa192, '192x192');
                $addIconVariants($pwa512, '512x512');
            } elseif ($has192) {
                $addIconVariants($pwa192, '192x192');
                $addIconVariants($pwa192, '512x512');
            } else {
                $addIconVariants($pwa512, '512x512');
                $addIconVariants($pwa512, '192x192');
            }
        } else {
            $iconsDir = public_path('icons');
            $file192 = is_file($iconsDir.'/icon-192x192.png');
            $file512 = is_file($iconsDir.'/icon-512x512.png');
            $icon192Url = url('/icons/icon-192x192.png');
            $icon512Url = url('/icons/icon-512x512.png');

            if ($file192) {
                $addIconVariants($icon192Url, '192x192');
            }
            if ($file512) {
                $addIconVariants($icon512Url, '512x512');
            }
            if (empty($icons)) {
                $fallbackIcon = (string) config('getfy.app_logo_icon', 'https://cdn.getfy.cloud/collapsed-logo.png');
                $addIconVariants($fallbackIcon, '192x192');
                $addIconVariants($fallbackIcon, '512x512');
            } elseif ($file512 && ! $file192) {
                $addIconVariants($icon512Url, '192x192');
            } elseif ($file192 && ! $file512) {
                $addIconVariants($icon192Url, '512x512');
            }
        }

        $manifest = [
            'id' => '/',
            'name' => $appName,
            'short_name' => $appName,
            'start_url' => '/login',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#18181b',
            'theme_color' => $themeColor,
            'prefer_related_applications' => false,
            'icons' => $icons,
        ];

        return response()
            ->json($manifest)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=0, must-revalidate');
    }

    private function withVersion(string $src, ?string $v): string
    {
        $src = trim($src);
        if ($src === '' || $v === null || $v === '') {
            return $src;
        }
        if (str_contains($src, 'v=')) {
            return $src;
        }
        return str_contains($src, '?') ? ($src.'&v='.$v) : ($src.'?v='.$v);
    }

    private function brandingVersionForRequest(Request $request): ?string
    {
        try {
            if (! Schema::hasTable('branding_settings')) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $user = $request->user();
        $tenantId = $user?->tenant_id;
        $tenantUpdatedAt = null;
        $globalUpdatedAt = null;

        if ($tenantId !== null) {
            $tenantUpdatedAt = BrandingSetting::query()
                ->where('tenant_id', $tenantId)
                ->value('updated_at');
        }
        $globalUpdatedAt = BrandingSetting::query()
            ->whereNull('tenant_id')
            ->value('updated_at');

        $best = $tenantUpdatedAt ?? $globalUpdatedAt;
        if (! $best) {
            return null;
        }

        try {
            return (string) \Illuminate\Support\Carbon::parse($best)->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }

    public function pushSubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys' => ['required', 'array'],
            'keys.auth' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! $user->canAccessPanel()) {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }

        $keys = $validated['keys'];
        $keys['auth'] = $this->normalizeBase64KeyForPush((string) ($keys['auth'] ?? ''));
        $keys['p256dh'] = $this->normalizeBase64KeyForPush((string) ($keys['p256dh'] ?? ''));

        $subscription = PanelPushSubscription::updateOrCreate(
            [
                'endpoint' => $validated['endpoint'],
            ],
            [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'keys' => $keys,
                'user_agent' => $request->userAgent(),
            ]
        );

        return response()->json([
            'success' => true,
            'subscribed' => true,
            'subscription_id' => $subscription->id,
            'updated_at' => $subscription->updated_at?->toISOString(),
        ]);
    }

    private function normalizeBase64KeyForPush(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return $key;
        }
        if (str_contains($key, '+') || str_contains($key, '/')) {
            return strtr($key, ['+' => '-', '/' => '_']);
        }

        return $key;
    }
}
