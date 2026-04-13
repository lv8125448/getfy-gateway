<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ApplyBrandingConfig;
use App\Models\BrandingSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BrandingSettingsController extends Controller
{
    private const TEXT_KEYS = [
        'app_name',
        'theme_primary',
        'app_logo',
        'app_logo_dark',
        'app_logo_icon',
        'app_logo_icon_dark',
        'login_hero_image',
        'favicon_url',
    ];

    private const UPLOAD_FIELDS = [
        'app_logo',
        'app_logo_dark',
        'app_logo_icon',
        'app_logo_icon_dark',
        'login_hero_image',
        'favicon_url',
    ];

    public function data(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $global = BrandingSetting::query()->whereNull('tenant_id')->first();
        $tenant = BrandingSetting::query()->where('tenant_id', $user->tenant_id)->first();
        $globalData = is_array($global?->data) ? $global->data : [];
        $tenantData = is_array($tenant?->data) ? $tenant->data : [];
        $branding = ApplyBrandingConfig::mergeLayers($globalData, $tenantData);

        return response()->json([
            'branding' => $branding,
            'can_sync_global' => $user->isAdmin(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $rules = [];
        foreach (self::TEXT_KEYS as $key) {
            $rules[$key] = ['nullable', 'string', 'max:2048'];
        }
        $validated = $request->validate($rules);

        $row = BrandingSetting::query()->firstOrCreate(
            ['tenant_id' => $user->tenant_id],
            ['data' => []]
        );

        $data = is_array($row->data) ? $row->data : [];
        foreach (self::TEXT_KEYS as $key) {
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

        // Garante sincronismo automático do nome do PWA global quando o admin altera o nome da aplicação.
        if ($user->isAdmin() && array_key_exists('app_name', $validated)) {
            $globalRow = BrandingSetting::query()->firstOrCreate(
                ['tenant_id' => null],
                ['data' => []]
            );
            $globalData = is_array($globalRow->data) ? $globalRow->data : [];
            $appName = $validated['app_name'] ?? null;
            if ($appName === null || trim((string) $appName) === '') {
                unset($globalData['app_name']);
            } else {
                $globalData['app_name'] = trim((string) $appName);
            }
            $globalRow->update(['data' => $globalData]);
        }

        return response()->json(['ok' => true]);
    }

    public function upload(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'field' => ['required', 'string', Rule::in(self::UPLOAD_FIELDS)],
            'file' => ['required', 'file', 'max:4096', 'mimes:jpg,jpeg,png,webp,gif,ico,svg'],
        ]);

        $path = $request->file('file')->store("white-label/{$user->tenant_id}", 'public');
        $url = Storage::disk('public')->url($path);
        if (! str_starts_with($url, 'http')) {
            $url = rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
        }

        $row = BrandingSetting::query()->firstOrCreate(
            ['tenant_id' => $user->tenant_id],
            ['data' => []]
        );
        $data = is_array($row->data) ? $row->data : [];
        $data[$validated['field']] = $url;
        $row->update(['data' => $data]);

        return response()->json(['ok' => true, 'url' => $url, 'field' => $validated['field']]);
    }

    public function clearField(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'field' => ['required', 'string', Rule::in(self::TEXT_KEYS)],
        ]);

        $row = BrandingSetting::query()->where('tenant_id', $user->tenant_id)->first();
        if (! $row) {
            return response()->json(['ok' => true]);
        }

        $data = is_array($row->data) ? $row->data : [];
        unset($data[$validated['field']]);
        $row->update(['data' => $data]);

        return response()->json(['ok' => true]);
    }

    public function syncGlobal(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Apenas administradores.'], 403);
        }

        $tenant = BrandingSetting::query()->where('tenant_id', $user->tenant_id)->first();
        if (! $tenant || empty($tenant->data) || ! is_array($tenant->data)) {
            return response()->json(['message' => 'Salve as configurações do tenant antes de copiar.'], 422);
        }

        BrandingSetting::query()->updateOrCreate(
            ['tenant_id' => null],
            ['data' => $tenant->data]
        );

        return response()->json(['ok' => true]);
    }
}
