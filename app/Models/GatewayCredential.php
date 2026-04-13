<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class GatewayCredential extends Model
{
    protected $fillable = [
        'tenant_id',
        'gateway_slug',
        'credentials',
        'is_connected',
    ];

    protected function casts(): array
    {
        return [
            'is_connected' => 'boolean',
        ];
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        if ($tenantId === null) {
            return $query->whereNull('tenant_id');
        }
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Credencial do tenant; se não houver (ou não conectada quando exigido), usa credencial global (tenant_id null).
     *
     * @deprecated Para cobrança use resolveForPayment (global primeiro).
     */
    public static function resolveConnected(?int $tenantId, string $gatewaySlug): ?self
    {
        if ($tenantId !== null) {
            $c = static::forTenant($tenantId)
                ->where('gateway_slug', $gatewaySlug)
                ->where('is_connected', true)
                ->first();
            if ($c !== null) {
                return $c;
            }
        }

        return static::forTenant(null)
            ->where('gateway_slug', $gatewaySlug)
            ->where('is_connected', true)
            ->first();
    }

    /**
     * Credencial usada em cobrança/checkout/webhooks: **global (admin) primeiro**, depois tenant.
     */
    public static function resolveForPayment(?int $tenantId, string $gatewaySlug): ?self
    {
        $global = static::forTenant(null)
            ->where('gateway_slug', $gatewaySlug)
            ->where('is_connected', true)
            ->first();
        if ($global !== null) {
            return $global;
        }
        if ($tenantId !== null) {
            return static::forTenant($tenantId)
                ->where('gateway_slug', $gatewaySlug)
                ->where('is_connected', true)
                ->first();
        }

        return null;
    }

    /**
     * Como resolveConnected, mas aceita credencial não conectada (ex.: fluxos que só leem chaves).
     */
    public static function resolveForTenantOrGlobal(?int $tenantId, string $gatewaySlug): ?self
    {
        if ($tenantId !== null) {
            $c = static::forTenant($tenantId)->where('gateway_slug', $gatewaySlug)->first();
            if ($c !== null) {
                return $c;
            }
        }

        return static::forTenant(null)->where('gateway_slug', $gatewaySlug)->first();
    }

    /**
     * Slug => credencial conectada; entradas do tenant sobrescrevem as globais.
     *
     * @return \Illuminate\Support\Collection<string, self>
     */
    public static function connectedMapForTenantOrGlobal(?int $tenantId): \Illuminate\Support\Collection
    {
        $global = static::forTenant(null)
            ->where('is_connected', true)
            ->get()
            ->keyBy('gateway_slug');
        if ($tenantId === null) {
            return $global;
        }
        $forTenant = static::forTenant($tenantId)
            ->where('is_connected', true)
            ->get()
            ->keyBy('gateway_slug');

        return $global->merge($forTenant);
    }

    /**
     * Mapa slug => credencial para UI/cobrança: **globais primeiro**, depois só slugs que existem só no tenant.
     *
     * @return \Illuminate\Support\Collection<string, self>
     */
    public static function connectedMapForPayment(?int $tenantId): \Illuminate\Support\Collection
    {
        $global = static::forTenant(null)
            ->where('is_connected', true)
            ->get()
            ->keyBy('gateway_slug');
        if ($tenantId === null) {
            return $global;
        }
        $forTenant = static::forTenant($tenantId)
            ->where('is_connected', true)
            ->get()
            ->keyBy('gateway_slug');
        $merged = collect();
        $slugs = $global->keys()->merge($forTenant->keys())->unique();
        foreach ($slugs as $slug) {
            $cred = $global->get($slug) ?? $forTenant->get($slug);
            if ($cred !== null) {
                $merged->put($slug, $cred);
            }
        }

        return $merged;
    }

    /**
     * Get decrypted credentials array. Never expose to serialization/API.
     *
     * @return array<string, string>
     */
    public function getDecryptedCredentials(): array
    {
        if (empty($this->credentials)) {
            return [];
        }
        try {
            $decrypted = Crypt::decryptString($this->credentials);
            $decoded = json_decode($decrypted, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            Log::warning('GatewayCredential getDecryptedCredentials failed', [
                'gateway_slug' => $this->gateway_slug,
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Set credentials from array (will be encrypted).
     *
     * @param  array<string, string>  $credentials
     */
    public function setEncryptedCredentials(array $credentials): void
    {
        $this->credentials = Crypt::encryptString(json_encode($credentials));
    }
}
