<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['tenant_id', 'key', 'value'];

    /**
     * Get setting for a tenant. When tenantId is not null, only that tenant's value is returned
     * (no fallback to master/global). When tenantId is null, only the global (tenant_id null) row is used.
     * This ensures each infoprodutor sees only their own settings (e.g. SMTP), not the master's.
     */
    public static function get(string $key, mixed $default = null, ?int $tenantId = null): mixed
    {
        $cacheKey = 'setting.'.($tenantId ?? 'global').'.'.$key;
        return Cache::remember($cacheKey, 60, function () use ($key, $default, $tenantId) {
            $query = static::query()->where('key', $key);
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            } else {
                $query->whereNull('tenant_id');
            }
            $row = $query->first();
            return $row ? $row->value : $default;
        });
    }

    public static function set(string $key, mixed $value, ?int $tenantId = null): void
    {
        static::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => $key],
            ['value' => is_string($value) ? $value : json_encode($value)]
        );
        Cache::forget('setting.'.($tenantId ?? 'global').'.'.$key);
    }
}
