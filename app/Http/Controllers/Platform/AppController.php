<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\BrandingSetting;
use App\Models\PanelPushSubscription;
use App\Services\PanelPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AppController extends Controller
{
    private const PWA_KEYS = [
        'app_name',
        'pwa_theme_color',
        'pwa_icon_192',
        'pwa_icon_512',
    ];

    private const UPLOAD_FIELDS = [
        'pwa_icon_192',
        'pwa_icon_512',
    ];

    public function index(): Response
    {
        $row = BrandingSetting::query()->whereNull('tenant_id')->first();
        $data = is_array($row?->data) ? $row->data : [];

        return Inertia::render('Platform/App/Index', [
            'app' => [
                'app_name' => (string) ($data['app_name'] ?? config('getfy.app_name', 'Getfy')),
                'pwa_theme_color' => (string) ($data['pwa_theme_color'] ?? config('getfy.pwa_theme_color', config('getfy.theme_primary', '#0ea5e9'))),
                'pwa_icon_192' => (string) ($data['pwa_icon_192'] ?? config('getfy.pwa_icon_192', '')),
                'pwa_icon_512' => (string) ($data['pwa_icon_512'] ?? config('getfy.pwa_icon_512', '')),
            ],
            'push_subscriptions_count' => PanelPushSubscription::query()->count(),
        ]);
    }

    public function data(): JsonResponse
    {
        $row = BrandingSetting::query()->whereNull('tenant_id')->first();
        $data = is_array($row?->data) ? $row->data : [];

        return response()->json([
            'app' => [
                'app_name' => (string) ($data['app_name'] ?? config('getfy.app_name', 'Getfy')),
                'pwa_theme_color' => (string) ($data['pwa_theme_color'] ?? config('getfy.pwa_theme_color', config('getfy.theme_primary', '#0ea5e9'))),
                'pwa_icon_192' => (string) ($data['pwa_icon_192'] ?? config('getfy.pwa_icon_192', '')),
                'pwa_icon_512' => (string) ($data['pwa_icon_512'] ?? config('getfy.pwa_icon_512', '')),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_name' => ['nullable', 'string', 'max:120'],
            'pwa_theme_color' => ['nullable', 'string', 'max:20'],
            'pwa_icon_192' => ['nullable', 'string', 'max:2048'],
            'pwa_icon_512' => ['nullable', 'string', 'max:2048'],
        ]);

        $row = BrandingSetting::query()->firstOrCreate(
            ['tenant_id' => null],
            ['data' => []]
        );
        $data = is_array($row->data) ? $row->data : [];
        foreach (self::PWA_KEYS as $key) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }
            $v = $validated[$key];
            if ($v === null || trim((string) $v) === '') {
                unset($data[$key]);
            } else {
                $data[$key] = trim((string) $v);
            }
        }
        $row->update(['data' => $data]);

        return response()->json(['ok' => true]);
    }

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'field' => ['required', 'string', Rule::in(self::UPLOAD_FIELDS)],
            'file' => ['required', 'file', 'max:4096', 'mimes:jpg,jpeg,png,webp,gif,ico,svg'],
        ]);

        $path = $request->file('file')->store('white-label/global', 'public');
        $url = Storage::disk('public')->url($path);
        if (! str_starts_with($url, 'http')) {
            $url = rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
        }

        $row = BrandingSetting::query()->firstOrCreate(
            ['tenant_id' => null],
            ['data' => []]
        );
        $data = is_array($row->data) ? $row->data : [];
        $data[$validated['field']] = $url;
        $row->update(['data' => $data]);

        return response()->json(['ok' => true, 'url' => $url, 'field' => $validated['field']]);
    }

    public function clearField(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'field' => ['required', 'string', Rule::in(self::PWA_KEYS)],
        ]);

        $row = BrandingSetting::query()->whereNull('tenant_id')->first();
        if (! $row) {
            return response()->json(['ok' => true]);
        }

        $data = is_array($row->data) ? $row->data : [];
        unset($data[$validated['field']]);
        $row->update(['data' => $data]);

        return response()->json(['ok' => true]);
    }

    public function sendPush(Request $request, PanelPushService $panelPushService): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
            'url' => ['nullable', 'string', 'max:2048'],
        ]);

        $result = $panelPushService->sendAndPersistToAll(
            'system',
            trim($validated['title']),
            trim($validated['body']),
            isset($validated['url']) && trim((string) $validated['url']) !== '' ? trim((string) $validated['url']) : null
        );

        return response()->json([
            'ok' => true,
            'result' => $result,
        ]);
    }
}
