@php
    $path = request()->path();
    $isMemberArea = str_starts_with($path, 'm/') || request()->attributes->get('member_area_slug');
    $isCheckout = str_starts_with($path, 'c/') || str_starts_with($path, 'checkout') || str_starts_with($path, 'api-checkout');
    $skipPanelPwa = $isMemberArea || $isCheckout;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        (function(){try{var s=localStorage.getItem('theme');var t=s||'dark';document.documentElement.classList.toggle('dark',t==='dark');}catch(_){}})();
    </script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('getfy.app_name', config('app.name', 'Getfy')) }}</title>
    @unless($skipPanelPwa)
    @php
        $wlFavicon = config('getfy.favicon_url');
        $wlFavicon = ($wlFavicon !== null && $wlFavicon !== '') ? $wlFavicon : 'https://cdn.getfy.cloud/collapsed-logo.png';
        $wlThemeColor = config('getfy.pwa_theme_color');
        $wlThemeColor = ($wlThemeColor !== null && $wlThemeColor !== '') ? $wlThemeColor : config('getfy.theme_primary', '#0ea5e9');
        $wlAppleIcon = config('getfy.pwa_icon_192');
        $wlVersion = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('branding_settings')) {
                $tenantId = auth()->check() ? auth()->user()?->tenant_id : null;
                $ts = null;
                if ($tenantId !== null) {
                    $ts = \App\Models\BrandingSetting::query()->where('tenant_id', $tenantId)->value('updated_at');
                }
                $ts = $ts ?: \App\Models\BrandingSetting::query()->whereNull('tenant_id')->value('updated_at');
                if ($ts) {
                    $wlVersion = (string) \Illuminate\Support\Carbon::parse($ts)->getTimestamp();
                }
            }
        } catch (\Throwable) {
            $wlVersion = null;
        }
        $wlWithVersion = function (string $url) use ($wlVersion): string {
            if (! $wlVersion) return $url;
            if (str_contains($url, 'v=')) return $url;
            return str_contains($url, '?') ? ($url.'&v='.$wlVersion) : ($url.'?v='.$wlVersion);
        };
    @endphp
    <link rel="icon" href="{{ $wlWithVersion($wlFavicon) }}" type="image/png">
    <link rel="manifest" href="{{ $wlWithVersion(url('/manifest.json')) }}">
    <meta name="theme-color" content="{{ $wlThemeColor }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    @if($wlAppleIcon !== null && $wlAppleIcon !== '')
    <link rel="apple-touch-icon" href="{{ $wlWithVersion($wlAppleIcon) }}">
    @elseif(is_file(public_path('icons/icon-192x192.png')))
    <link rel="apple-touch-icon" href="{{ $wlWithVersion(url('/icons/icon-192x192.png')) }}">
    @elseif(is_file(public_path('icons/icon-512x512.png')))
    <link rel="apple-touch-icon" href="{{ $wlWithVersion(url('/icons/icon-512x512.png')) }}">
    @endif
    <script>
        (function(){var e=null;window.addEventListener('beforeinstallprompt',function(t){t.preventDefault();e=t;window.__pwaInstallPrompt=e;},{capture:true});Object.defineProperty(window,'__pwaInstallPrompt',{get:function(){return e;},set:function(t){e=t;}});})();
    </script>
    @endunless
    @inertiaHead
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    @php
        $page = $page ?? [];
        $page['props'] = array_merge(
            [
                'auth' => ['user' => null],
                'flash' => ['success' => null, 'error' => null],
                'platform' => null,
            ],
            $page['props'] ?? []
        );
    @endphp
    <div id="app" data-page="{{ json_encode($page) }}"></div>
</body>
</html>
