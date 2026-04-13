<?php

namespace App\Http\Controllers\Concerns;

use App\Gateways\GatewayRegistry;
use App\Models\GatewayCredential;
use App\Models\Setting;

trait ProvidesPlatformGatewayProps
{
    /**
     * @return array{pix: array<int, string>, card: array<int, string>, boleto: array<int, string>, pix_auto: array<int, string>}
     */
    protected function buildGatewayOrderForSettings(?int $tenantId): array
    {
        $gatewayOrderRaw = Setting::get('gateway_order', null, $tenantId);
        $default = config('gateways.default_order', ['pix' => [], 'card' => [], 'boleto' => [], 'pix_auto' => []]);
        $gatewayOrder = is_string($gatewayOrderRaw)
            ? (json_decode($gatewayOrderRaw, true) ?: $default)
            : (is_array($gatewayOrderRaw) ? $gatewayOrderRaw : $default);

        return [
            'pix' => GatewayRegistry::filterSlugsToAllowedAcquirers($gatewayOrder['pix'] ?? []),
            'card' => GatewayRegistry::filterSlugsToAllowedAcquirers($gatewayOrder['card'] ?? []),
            'boleto' => GatewayRegistry::filterSlugsToAllowedAcquirers($gatewayOrder['boleto'] ?? []),
            'pix_auto' => GatewayRegistry::filterSlugsToAllowedAcquirers($gatewayOrder['pix_auto'] ?? []),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildGatewaysListForSettings(?int $tenantId): array
    {
        $all = GatewayRegistry::allowedAcquirers();
        $credentialBySlug = GatewayCredential::forTenant($tenantId)->get()->keyBy('gateway_slug');

        return array_map(function ($g) use ($credentialBySlug) {
            $cred = $credentialBySlug->get($g['slug'] ?? '');
            $image = $g['image'] ?? null;

            return [
                'slug' => $g['slug'],
                'name' => $g['name'],
                'image' => GatewayRegistry::resolveImageUrl(is_string($image) ? $image : null),
                'methods' => $g['methods'] ?? [],
                'scope' => $g['scope'] ?? 'national',
                'country' => $g['country'] ?? null,
                'country_name' => $g['country_name'] ?? null,
                'country_flag' => $g['country_flag'] ?? null,
                'countries' => $g['countries'] ?? null,
                'signup_url' => $g['signup_url'] ?? null,
                'is_configured' => $cred !== null,
                'is_connected' => $cred?->is_connected ?? false,
            ];
        }, $all);
    }

    /**
     * Lista para o modal «ordem por infoprodutor»: mesmo registo de adquirentes, mas
     * {@see $g['is_connected']} é true se existir credencial conectada em qualquer tenant
     * ou global — evita dropdown vazio quando só há credenciais por tenant.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildGatewaysListForMerchantPicker(): array
    {
        $all = GatewayRegistry::allowedAcquirers();
        $connectedSlugs = GatewayCredential::query()
            ->where('is_connected', true)
            ->pluck('gateway_slug')
            ->unique()
            ->all();
        $connectedSet = array_fill_keys($connectedSlugs, true);
        $configuredSlugs = GatewayCredential::query()
            ->pluck('gateway_slug')
            ->unique()
            ->all();
        $configuredSet = array_fill_keys($configuredSlugs, true);

        return array_map(function ($g) use ($connectedSet, $configuredSet) {
            $slug = $g['slug'] ?? '';
            $image = $g['image'] ?? null;

            return [
                'slug' => $slug,
                'name' => $g['name'],
                'image' => GatewayRegistry::resolveImageUrl(is_string($image) ? $image : null),
                'methods' => $g['methods'] ?? [],
                'scope' => $g['scope'] ?? 'national',
                'country' => $g['country'] ?? null,
                'country_name' => $g['country_name'] ?? null,
                'country_flag' => $g['country_flag'] ?? null,
                'countries' => $g['countries'] ?? null,
                'signup_url' => $g['signup_url'] ?? null,
                'is_configured' => isset($configuredSet[$slug]),
                'is_connected' => isset($connectedSet[$slug]),
            ];
        }, $all);
    }
}
