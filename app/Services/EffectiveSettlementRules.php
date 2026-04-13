<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;

class EffectiveSettlementRules
{
    /**
     * @return array{pix: array{days_to_available: int, reserve_percent: float, reserve_hold_days: int}, card: array{days_to_available: int, reserve_percent: float, reserve_hold_days: int}, boleto: array{days_to_available: int, reserve_percent: float, reserve_hold_days: int}}
     */
    public static function platformDefaults(): array
    {
        $raw = Setting::get('merchant_settlement_rules', null, null);
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        $base = [
            'pix' => ['days_to_available' => 0, 'reserve_percent' => 0.0, 'reserve_hold_days' => 0],
            'card' => ['days_to_available' => 0, 'reserve_percent' => 0.0, 'reserve_hold_days' => 0],
            'boleto' => ['days_to_available' => 0, 'reserve_percent' => 0.0, 'reserve_hold_days' => 0],
        ];
        if (! is_array($raw)) {
            return $base;
        }
        foreach (['pix', 'card', 'boleto'] as $k) {
            if (! isset($raw[$k]) || ! is_array($raw[$k])) {
                continue;
            }
            $base[$k]['days_to_available'] = max(0, (int) ($raw[$k]['days_to_available'] ?? 0));
            $base[$k]['reserve_percent'] = min(100, max(0, (float) ($raw[$k]['reserve_percent'] ?? 0)));
            $base[$k]['reserve_hold_days'] = max(0, min(365, (int) ($raw[$k]['reserve_hold_days'] ?? 0)));
        }

        return $base;
    }

    /**
     * @param  'pix'|'card'|'boleto'  $methodKey
     * @return array{days_to_available: int, reserve_percent: float, reserve_hold_days: int}
     */
    public static function forTenantMethod(int $tenantId, string $methodKey): array
    {
        if (! in_array($methodKey, ['pix', 'card', 'boleto'], true)) {
            $methodKey = 'pix';
        }
        $defaults = self::platformDefaults()[$methodKey];
        $owner = User::query()
            ->where('tenant_id', $tenantId)
            ->where('role', User::ROLE_INFOPRODUTOR)
            ->first();
        if ($owner === null) {
            $owner = User::query()->where('id', $tenantId)->where('role', User::ROLE_INFOPRODUTOR)->first();
        }
        if ($owner === null) {
            return $defaults;
        }
        $ov = $owner->merchant_settlement_overrides;
        if (! is_array($ov) || ! isset($ov[$methodKey]) || ! is_array($ov[$methodKey])) {
            return $defaults;
        }
        $block = $ov[$methodKey];
        if (array_key_exists('days_to_available', $block) && $block['days_to_available'] !== '' && $block['days_to_available'] !== null) {
            $defaults['days_to_available'] = max(0, (int) $block['days_to_available']);
        }
        if (array_key_exists('reserve_percent', $block) && $block['reserve_percent'] !== '' && $block['reserve_percent'] !== null) {
            $defaults['reserve_percent'] = min(100, max(0, (float) $block['reserve_percent']));
        }
        if (array_key_exists('reserve_hold_days', $block) && $block['reserve_hold_days'] !== '' && $block['reserve_hold_days'] !== null) {
            $defaults['reserve_hold_days'] = max(0, min(365, (int) $block['reserve_hold_days']));
        }

        return $defaults;
    }
}
