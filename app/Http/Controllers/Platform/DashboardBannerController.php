<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DashboardBannerController extends Controller
{
    private const KEY = 'dashboard_banners';

    public function data(): JsonResponse
    {
        return response()->json([
            'banners' => $this->getBanners(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'banners' => ['required', 'array', 'max:20'],
            'banners.*.id' => ['required', 'string', 'max:50'],
            'banners.*.title' => ['nullable', 'string', 'max:120'],
            'banners.*.desktop_url' => ['nullable', 'string', 'max:2048'],
            'banners.*.mobile_url' => ['nullable', 'string', 'max:2048'],
            'banners.*.active' => ['nullable', 'boolean'],
            'banners.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $normalized = collect($validated['banners'] ?? [])
            ->map(function (array $item, int $idx) {
                return [
                    'id' => (string) ($item['id'] ?? ('banner-'.$idx)),
                    'title' => trim((string) ($item['title'] ?? '')),
                    'desktop_url' => trim((string) ($item['desktop_url'] ?? '')),
                    'mobile_url' => trim((string) ($item['mobile_url'] ?? '')),
                    'active' => (bool) ($item['active'] ?? true),
                    'sort_order' => (int) ($item['sort_order'] ?? ($idx + 1)),
                ];
            })
            ->filter(fn (array $item) => $item['desktop_url'] !== '' || $item['mobile_url'] !== '')
            ->sortBy('sort_order')
            ->values()
            ->all();

        Setting::set(self::KEY, $normalized, null);

        return response()->json([
            'ok' => true,
            'banners' => $normalized,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:8192', 'mimes:jpg,jpeg,png,webp,gif,svg'],
            'variant' => ['required', 'string', Rule::in(['desktop', 'mobile'])],
        ]);

        $path = $request->file('file')->store('dashboard-banners', 'public');
        $url = Storage::disk('public')->url($path);
        if (! str_starts_with($url, 'http')) {
            $url = rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
        }

        return response()->json([
            'ok' => true,
            'url' => $url,
            'variant' => $validated['variant'],
        ]);
    }

    /**
     * @return array<int, array{id:string,title:string,desktop_url:string,mobile_url:string,active:bool,sort_order:int}>
     */
    private function getBanners(): array
    {
        $raw = Setting::get(self::KEY, [], null);
        $rows = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item, int $idx) {
                return [
                    'id' => (string) ($item['id'] ?? ('banner-'.$idx)),
                    'title' => (string) ($item['title'] ?? ''),
                    'desktop_url' => (string) ($item['desktop_url'] ?? ''),
                    'mobile_url' => (string) ($item['mobile_url'] ?? ''),
                    'active' => (bool) ($item['active'] ?? true),
                    'sort_order' => (int) ($item['sort_order'] ?? ($idx + 1)),
                ];
            })
            ->sortBy('sort_order')
            ->values()
            ->all();
    }
}
