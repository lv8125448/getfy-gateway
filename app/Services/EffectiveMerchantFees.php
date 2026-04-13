<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;

class EffectiveMerchantFees
{
    /**
     * @return array{pix: array{percent: float, fixed: float}, card: array{percent: float, fixed: float}, boleto: array{percent: float, fixed: float}, withdrawal: array{percent: float, fixed: float}}
     */
    public static function platformDefaults(): array
    {
        $raw = Setting::get('merchant_fee_rules', null, null);
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        $base = [
            'pix' => ['percent' => 0.0, 'fixed' => 0.0],
            'card' => ['percent' => 0.0, 'fixed' => 0.0],
            'boleto' => ['percent' => 0.0, 'fixed' => 0.0],
            'withdrawal' => ['percent' => 0.0, 'fixed' => 0.0],
        ];
        if (! is_array($raw)) {
            return $base;
        }
        foreach (['pix', 'card', 'boleto', 'withdrawal'] as $k) {
            if (! isset($raw[$k]) || ! is_array($raw[$k])) {
                continue;
            }
            $base[$k]['percent'] = (float) ($raw[$k]['percent'] ?? 0);
            $base[$k]['fixed'] = (float) ($raw[$k]['fixed'] ?? 0);
        }

        return $base;
    }

    /**
     * @return array{pix: array{percent: float, fixed: float}, card: array{percent: float, fixed: float}, boleto: array{percent: float, fixed: float}, withdrawal: array{percent: float, fixed: float}}
     */
    public static function forTenant(int $tenantId): array
    {
        $defaults = self::platformDefaults();
        $owner = User::query()
            ->where('tenant_id', $tenantId)
            ->where('role', User::ROLE_INFOPRODUTOR)
            ->first();
        if ($owner === null) {
            $owner = User::query()->where('id', $tenantId)->where('role', User::ROLE_INFOPRODUTOR)->first();
        }
        if ($owner === null || empty($owner->merchant_fees) || ! is_array($owner->merchant_fees)) {
            return $defaults;
        }
        $ov = $owner->merchant_fees;
        foreach (['pix', 'card', 'boleto', 'withdrawal'] as $k) {
            if (! isset($ov[$k]) || ! is_array($ov[$k])) {
                continue;
            }
            if (array_key_exists('percent', $ov[$k])) {
                $defaults[$k]['percent'] = (float) $ov[$k]['percent'];
            }
            if (array_key_exists('fixed', $ov[$k])) {
                $defaults[$k]['fixed'] = (float) $ov[$k]['fixed'];
            }
        }

        return $defaults;
    }

    /**
     * @param  'pix'|'card'|'boleto'|'withdrawal'  $method
     * @return array{fee: float, net: float, gross: float, percent: float, fixed: float}
     */
    public static function calculateSaleFee(int $tenantId, string $method, float $gross): array
    {
        $map = ['pix' => 'pix', 'card' => 'card', 'boleto' => 'boleto', 'pix_auto' => 'pix'];
        $key = $map[$method] ?? $method;
        if (! in_array($key, ['pix', 'card', 'boleto'], true)) {
            $key = 'pix';
        }
        $rules = self::forTenant($tenantId);
        $percent = $rules[$key]['percent'];
        $fixed = $rules[$key]['fixed'];
        $fee = round($gross * ($percent / 100.0) + $fixed, 2);
        if ($fee > $gross) {
            $fee = $gross;
        }
        $net = round($gross - $fee, 2);

        return [
            'fee' => $fee,
            'net' => $net,
            'gross' => $gross,
            'percent' => $percent,
            'fixed' => $fixed,
        ];
    }

    /**
     * Taxa sobre valor solicitado de saque (bruto).
     *
     * @return array{fee: float, net: float, gross: float}
     */
    public static function calculateWithdrawalFee(int $tenantId, float $requestedAmount): array
    {
        $rules = self::forTenant($tenantId);
        $percent = $rules['withdrawal']['percent'];
        $fixed = $rules['withdrawal']['fixed'];
        $fee = round($requestedAmount * ($percent / 100.0) + $fixed, 2);
        if ($fee > $requestedAmount) {
            $fee = $requestedAmount;
        }
        $net = round($requestedAmount - $fee, 2);

        return ['fee' => $fee, 'net' => $net, 'gross' => $requestedAmount];
    }

    /**
     * Menor valor bruto (solicitado) tal que o líquido após taxa da plataforma seja >= targetNet.
     */
    public static function minimumWithdrawalGrossForTargetNet(int $tenantId, float $targetNet): ?float
    {
        if ($targetNet <= 0) {
            return 0.01;
        }

        $rules = self::forTenant($tenantId);
        $p = (float) $rules['withdrawal']['percent'];
        $f = (float) $rules['withdrawal']['fixed'];
        $rate = 1 - ($p / 100.0);
        if ($rate <= 0) {
            return null;
        }

        $g = max(0.01, round(($targetNet + $f) / $rate, 2));

        for ($i = 0; $i < 500000; $i++) {
            $calc = self::calculateWithdrawalFee($tenantId, $g);
            if ($calc['net'] + 0.0001 >= $targetNet) {
                return round($g, 2);
            }
            $g = round($g + 0.01, 2);
        }

        return null;
    }
}
