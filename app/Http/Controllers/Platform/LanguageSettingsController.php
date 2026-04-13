<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformLanguage;
use App\Models\PlatformTranslation;
use App\Services\PlatformI18nService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LanguageSettingsController extends Controller
{
    public function data(Request $request, PlatformI18nService $i18n): JsonResponse
    {
        $this->ensureConfiguredLanguagesExist();

        $languages = $i18n->activeLanguages();
        $allLanguages = PlatformLanguage::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (PlatformLanguage $l) => [
                'id' => $l->id,
                'code' => (string) $l->code,
                'name' => (string) $l->name,
                'is_default' => (bool) $l->is_default,
                'is_active' => (bool) $l->is_active,
                'sort_order' => (int) $l->sort_order,
            ])
            ->values()
            ->all();
        $selected = (string) $request->query('locale', $i18n->defaultLocale());
        $keys = $i18n->allKeys('seller');
        $values = $i18n->messagesFor($selected, 'seller');

        return response()->json([
            'selected_locale' => $selected,
            'default_locale' => $i18n->defaultLocale(),
            'languages' => $allLanguages,
            'active_languages' => $languages,
            'keys' => $keys,
            'values' => $values,
        ]);
    }

    public function addLanguage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('platform_languages', 'code')],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $maxSort = (int) PlatformLanguage::query()->max('sort_order');
        PlatformLanguage::query()->create([
            'code' => trim((string) $validated['code']),
            'name' => trim((string) $validated['name']),
            'is_active' => true,
            'is_default' => false,
            'sort_order' => $maxSort + 1,
        ]);

        return response()->json(['ok' => true]);
    }

    public function updateLanguage(Request $request, PlatformLanguage $platformLanguage): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (array_key_exists('is_default', $validated) && $validated['is_default']) {
            PlatformLanguage::query()->where('id', '!=', $platformLanguage->id)->update(['is_default' => false]);
            $validated['is_active'] = true;
        }

        if (($validated['is_active'] ?? true) === false && $platformLanguage->is_default) {
            return response()->json(['message' => 'O idioma padrão não pode ser desativado.'], 422);
        }

        $platformLanguage->update(array_filter([
            'name' => isset($validated['name']) ? trim((string) $validated['name']) : null,
            'is_active' => $validated['is_active'] ?? null,
            'is_default' => $validated['is_default'] ?? null,
            'sort_order' => $validated['sort_order'] ?? null,
        ], fn ($v) => $v !== null));

        return response()->json(['ok' => true]);
    }

    public function saveTranslations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:20'],
            'translations' => ['required', 'array'],
            'translations.*' => ['nullable', 'string'],
        ]);

        $locale = trim((string) $validated['locale']);
        $translations = (array) $validated['translations'];
        $now = now();
        foreach ($translations as $key => $value) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }
            PlatformTranslation::query()->updateOrCreate(
                [
                    'group' => 'seller',
                    'key' => $normalizedKey,
                    'locale' => $locale,
                ],
                [
                    'value' => $value === null ? '' : (string) $value,
                    'updated_at' => $now,
                ]
            );
        }

        return response()->json(['ok' => true]);
    }

    public function importMissing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:20'],
        ]);
        $locale = (string) $validated['locale'];
        $fallback = (array) config('panel_i18n.locales.pt_BR', []);
        $targetDefaults = (array) config("panel_i18n.locales.$locale", []);

        foreach ($fallback as $key => $ptValue) {
            $has = PlatformTranslation::query()
                ->where('group', 'seller')
                ->where('key', $key)
                ->where('locale', $locale)
                ->exists();
            if ($has) {
                continue;
            }
            PlatformTranslation::query()->create([
                'group' => 'seller',
                'key' => (string) $key,
                'locale' => $locale,
                'value' => (string) ($targetDefaults[$key] ?? $ptValue),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function ensureConfiguredLanguagesExist(): void
    {
        $configuredLocales = array_keys((array) config('panel_i18n.locales', []));
        if (empty($configuredLocales)) {
            return;
        }

        $existingCodes = PlatformLanguage::query()
            ->pluck('code')
            ->map(fn ($code) => (string) $code)
            ->all();

        $maxSort = (int) PlatformLanguage::query()->max('sort_order');
        foreach ($configuredLocales as $code) {
            $locale = trim((string) $code);
            if ($locale === '' || in_array($locale, $existingCodes, true)) {
                continue;
            }

            $maxSort++;
            PlatformLanguage::query()->create([
                'code' => $locale,
                'name' => $this->localeDisplayName($locale),
                'is_active' => true,
                'is_default' => false,
                'sort_order' => $maxSort,
            ]);
            $existingCodes[] = $locale;
        }
    }

    private function localeDisplayName(string $locale): string
    {
        return match ($locale) {
            'pt_BR' => 'Português (Brasil)',
            'en' => 'English',
            'es' => 'Español',
            default => strtoupper($locale),
        };
    }
}
