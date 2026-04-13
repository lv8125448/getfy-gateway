<?php

namespace App\Support;

class CheckoutTranslations
{
    /**
     * Merge default checkout translations with saved ones.
     * Ensures every key from defaults exists; saved values override.
     *
     * @param  array<string, array<string, string>>  $defaults  e.g. config('checkout_translations')
     * @param  array<string, array<string, string>>  $saved  tenant saved translations
     * @return array<string, array<string, string>>
     */
    public static function merge(array $defaults, array $saved): array
    {
        $locales = array_keys($defaults);
        $merged = [];
        foreach ($locales as $locale) {
            $defaultLocale = is_array($defaults[$locale] ?? null) ? $defaults[$locale] : [];
            $savedLocale = is_array($saved[$locale] ?? null) ? $saved[$locale] : [];
            $merged[$locale] = array_merge($defaultLocale, $savedLocale);
        }
        return $merged;
    }
}
