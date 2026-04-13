<?php

namespace App\Services;

use App\Models\PlatformLanguage;
use App\Models\PlatformTranslation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PlatformI18nService
{
    private const SESSION_KEY = 'panel_locale';

    public function resolveLocale(Request $request): string
    {
        $fallback = (string) config('panel_i18n.default_locale', 'pt_BR');
        $sessionLocale = '';
        if ($request->hasSession()) {
            $sessionLocale = (string) $request->session()->get(self::SESSION_KEY, '');
        }
        if ($sessionLocale !== '' && $this->isLocaleActive($sessionLocale)) {
            return $sessionLocale;
        }

        $default = $this->defaultLocale();

        return $default ?: $fallback;
    }

    public function persistLocale(Request $request, string $locale): string
    {
        $normalized = trim($locale);
        if ($normalized === '' || ! $this->isLocaleActive($normalized)) {
            $normalized = $this->defaultLocale();
        }
        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $normalized);
        }

        return $normalized;
    }

    public function defaultLocale(): string
    {
        $fallback = (string) config('panel_i18n.default_locale', 'pt_BR');
        if (! Schema::hasTable('platform_languages')) {
            return $fallback;
        }
        $row = PlatformLanguage::query()
            ->where('is_default', true)
            ->first();
        if ($row) {
            return (string) $row->code;
        }

        $first = PlatformLanguage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        return $first ? (string) $first->code : $fallback;
    }

    public function activeLanguages(): array
    {
        if (! Schema::hasTable('platform_languages')) {
            return [
                ['code' => 'pt_BR', 'name' => 'Português (Brasil)', 'is_default' => true, 'is_active' => true],
                ['code' => 'en', 'name' => 'English', 'is_default' => false, 'is_active' => true],
                ['code' => 'es', 'name' => 'Español', 'is_default' => false, 'is_active' => true],
            ];
        }

        $active = PlatformLanguage::query()
            ->where('is_active', true)
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

        $activeCodes = array_map(fn (array $l) => (string) ($l['code'] ?? ''), $active);
        $existingCodes = PlatformLanguage::query()
            ->pluck('code')
            ->map(fn ($code) => (string) $code)
            ->all();
        $defaultLocale = $this->defaultLocale();

        // Fallback: if a configured locale is missing in DB entirely, expose it as active.
        foreach (array_keys((array) config('panel_i18n.locales', [])) as $code) {
            $locale = (string) $code;
            if (in_array($locale, $activeCodes, true) || in_array($locale, $existingCodes, true)) {
                continue;
            }

            $active[] = [
                'code' => $locale,
                'name' => $this->localeDisplayName($locale),
                'is_default' => $locale === $defaultLocale,
                'is_active' => true,
                'sort_order' => 9999,
            ];
        }

        return $active;
    }

    public function messagesFor(string $locale, string $group = 'seller'): array
    {
        $defaults = (array) config("panel_i18n.locales.$locale", []);
        if (! Schema::hasTable('platform_translations')) {
            return $defaults;
        }

        $overrides = PlatformTranslation::query()
            ->where('group', $group)
            ->where('locale', $locale)
            ->get(['key', 'value'])
            ->mapWithKeys(fn (PlatformTranslation $t) => [(string) $t->key => (string) ($t->value ?? '')])
            ->all();

        return array_merge($defaults, $overrides);
    }

    public function allKeys(string $group = 'seller'): array
    {
        $defaultKeys = [];
        $allDefault = (array) config('panel_i18n.locales', []);
        foreach ($allDefault as $rows) {
            $defaultKeys = array_merge($defaultKeys, array_keys((array) $rows));
        }

        $dbKeys = [];
        if (Schema::hasTable('platform_translations')) {
            $dbKeys = PlatformTranslation::query()
                ->where('group', $group)
                ->distinct()
                ->pluck('key')
                ->map(fn ($k) => (string) $k)
                ->all();
        }

        $keys = array_values(array_unique(array_merge($defaultKeys, $dbKeys)));
        sort($keys);

        return $keys;
    }

    private function isLocaleActive(string $locale): bool
    {
        if (! Schema::hasTable('platform_languages')) {
            return in_array($locale, ['pt_BR', 'en', 'es'], true);
        }

        $row = PlatformLanguage::query()
            ->where('code', $locale)
            ->first();

        if ($row) {
            return (bool) $row->is_active;
        }

        // If locale is configured but not yet persisted in DB, allow selection via config fallback.
        return array_key_exists($locale, (array) config('panel_i18n.locales', []));
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
