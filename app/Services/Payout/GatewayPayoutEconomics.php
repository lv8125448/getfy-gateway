<?php

namespace App\Services\Payout;

use App\Models\GatewayCredential;

class GatewayPayoutEconomics
{
    public const DEFAULT_MIN_PAYOUT_BRL = 7.0;

    /**
     * Economia mínima para o gateway de payout ativo (primeiro conectado na ordem fixa).
     *
     * @return array{
     *     required_min_net: float,
     *     payout_min_brl: float,
     *     admin_fee_pix_brl: float,
     *     admin_fee_payout_brl: float
     * }
     */
    public static function forActiveGateway(): array
    {
        $slug = PlatformPayoutGateway::activeSlug();
        if ($slug === null) {
            return self::defaults();
        }

        return self::fromSlug($slug);
    }

    /**
     * @return array{
     *     required_min_net: float,
     *     payout_min_brl: float,
     *     admin_fee_pix_brl: float,
     *     admin_fee_payout_brl: float
     * }
     */
    public static function fromSlug(string $slug): array
    {
        $cred = GatewayCredential::resolveForPayment(null, $slug);
        if ($cred === null || ! $cred->is_connected) {
            return self::defaults();
        }

        return self::fromCredentialsArray($slug, $cred->getDecryptedCredentials());
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{
     *     required_min_net: float,
     *     payout_min_brl: float,
     *     admin_fee_pix_brl: float,
     *     admin_fee_payout_brl: float
     * }
     */
    public static function fromCredentialsArray(string $slug, array $credentials): array
    {
        if ($slug === 'cajupay') {
            $minPayout = self::parseNonNegative($credentials['cajupay_payout_min_brl'] ?? null, self::DEFAULT_MIN_PAYOUT_BRL);
            $feePix = self::parseNonNegative($credentials['cajupay_admin_fee_pix_brl'] ?? null, 0.0);
            $feePayout = self::parseNonNegative($credentials['cajupay_admin_fee_payout_brl'] ?? null, 0.0);
        } elseif ($slug === 'spacepag') {
            $minPayout = self::parseNonNegative($credentials['spacepag_payout_min_brl'] ?? null, self::DEFAULT_MIN_PAYOUT_BRL);
            $feePix = self::parseNonNegative($credentials['spacepag_admin_fee_pix_brl'] ?? null, 0.0);
            $feePayout = self::parseNonNegative($credentials['spacepag_admin_fee_payout_brl'] ?? null, 0.0);
        } elseif ($slug === 'woovi') {
            $minPayout = self::parseNonNegative($credentials['woovi_payout_min_brl'] ?? null, self::DEFAULT_MIN_PAYOUT_BRL);
            $feePix = self::parseNonNegative($credentials['woovi_admin_fee_pix_brl'] ?? null, 0.0);
            $feePayout = self::parseNonNegative($credentials['woovi_admin_fee_payout_brl'] ?? null, 0.0);
        } else {
            return self::defaults();
        }

        $required = round($minPayout + $feePix + $feePayout, 2);

        return [
            'required_min_net' => $required,
            'payout_min_brl' => $minPayout,
            'admin_fee_pix_brl' => $feePix,
            'admin_fee_payout_brl' => $feePayout,
        ];
    }

    /**
     * @return array{
     *     required_min_net: float,
     *     payout_min_brl: float,
     *     admin_fee_pix_brl: float,
     *     admin_fee_payout_brl: float
     * }
     */
    private static function defaults(): array
    {
        return [
            'required_min_net' => self::DEFAULT_MIN_PAYOUT_BRL,
            'payout_min_brl' => self::DEFAULT_MIN_PAYOUT_BRL,
            'admin_fee_pix_brl' => 0.0,
            'admin_fee_payout_brl' => 0.0,
        ];
    }

    private static function parseNonNegative(mixed $value, float $defaultWhenEmpty): float
    {
        if ($value === null) {
            return $defaultWhenEmpty;
        }
        if (is_numeric($value)) {
            return max(0.0, round((float) $value, 2));
        }
        $s = trim((string) $value);
        if ($s === '') {
            return $defaultWhenEmpty;
        }
        $normalized = str_replace([' ', ','], ['', '.'], $s);
        if ($normalized === '' || ! is_numeric($normalized)) {
            return $defaultWhenEmpty;
        }

        return max(0.0, round((float) $normalized, 2));
    }

    /**
     * Valor a enviar na API de payout quando o adquirente desconta a taxa admin do valor da ordem:
     * o infoprodutor deve receber exatamente o líquido informado em $sellerNet.
     */
    public static function transferAmountBrlForApi(float $sellerNet, float $adminFeePayoutBrl): float
    {
        return round($sellerNet + max(0.0, $adminFeePayoutBrl), 2);
    }
}
