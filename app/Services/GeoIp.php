<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeoIp
{
    /** Locales suportados no checkout */
    public const LOCALE_PT_BR = 'pt_BR';

    public const LOCALE_EN = 'en';

    public const LOCALE_ES = 'es';

    /** Moedas suportadas no checkout */
    public const CURRENCY_BRL = 'BRL';

    public const CURRENCY_USD = 'USD';

    public const CURRENCY_EUR = 'EUR';

    /** Países que usam euro (sigla ISO 3166-1 alpha-2) */
    private const EUR_COUNTRIES = [
        'AT', 'BE', 'CY', 'DE', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PT', 'SI', 'SK',
    ];

    private const LOCALE_BY_COUNTRY = [
        'BR' => self::LOCALE_PT_BR,
        'ES' => self::LOCALE_ES,
        'MX' => self::LOCALE_ES,
        'AR' => self::LOCALE_ES,
        'CO' => self::LOCALE_ES,
        'CL' => self::LOCALE_ES,
        'PE' => self::LOCALE_ES,
        'US' => self::LOCALE_EN,
        'GB' => self::LOCALE_EN,
        'CA' => self::LOCALE_EN,
        'AU' => self::LOCALE_EN,
        'IN' => self::LOCALE_EN,
    ];

    private int $cacheTtlSeconds = 86400; // 1 day

    public function __construct(?int $cacheTtlSeconds = null)
    {
        if ($cacheTtlSeconds !== null) {
            $this->cacheTtlSeconds = $cacheTtlSeconds;
        }
    }

    /**
     * Obtém país (e sugestões de locale/moeda) a partir do IP.
     * Usa cache por IP.
     *
     * @return array{country_code: string|null, suggested_locale: string, suggested_currency: string}
     */
    public function getSuggestionsForIp(string $ip): array
    {
        $cacheKey = 'geo_ip:'.md5($ip);
        $countryCode = Cache::remember($cacheKey, $this->cacheTtlSeconds, function () use ($ip) {
            return $this->fetchCountryCode($ip);
        });
        if ($countryCode === null) {
            return [
                'country_code' => null,
                'suggested_locale' => self::LOCALE_PT_BR,
                'suggested_currency' => self::CURRENCY_BRL, // fallback: Brasil como padrão (localhost/indeterminado)
            ];
        }
        return [
            'country_code' => $countryCode,
            'suggested_locale' => $this->localeForCountry($countryCode),
            'suggested_currency' => $this->currencyForCountry($countryCode),
        ];
    }

    private function fetchCountryCode(string $ip): ?string
    {
        if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
            return null;
        }
        try {
            $url = 'https://ip-api.com/json/'.urlencode($ip).'?fields=countryCode';
            $response = Http::timeout(3)->get($url);
            if (! $response->successful()) {
                return null;
            }
            $data = $response->json();
            $code = $data['countryCode'] ?? null;
            return is_string($code) && strlen($code) === 2 ? strtoupper($code) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function localeForCountry(string $countryCode): string
    {
        return self::LOCALE_BY_COUNTRY[$countryCode] ?? self::LOCALE_EN;
    }

    private function currencyForCountry(string $countryCode): string
    {
        if ($countryCode === 'BR') {
            return self::CURRENCY_BRL;
        }
        if (in_array($countryCode, self::EUR_COUNTRIES, true)) {
            return self::CURRENCY_EUR;
        }
        return self::CURRENCY_USD;
    }
}
