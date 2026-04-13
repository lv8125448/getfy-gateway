<?php

namespace App\Services\CajuPay;

use App\Models\GatewayCredential;
use App\Services\Payout\GatewayPayoutEconomics;

class CajuPayCredentialEconomics
{
    public const DEFAULT_MIN_PAYOUT_BRL = GatewayPayoutEconomics::DEFAULT_MIN_PAYOUT_BRL;

    /**
     * Líquido mínimo exigido: mínimo CajuPay + taxas PIX/saque (referência admin).
     *
     * @return array{
     *     required_min_net: float,
     *     cajupay_payout_min_brl: float,
     *     cajupay_admin_fee_pix_brl: float,
     *     cajupay_admin_fee_payout_brl: float
     * }
     */
    public static function fromGateway(): array
    {
        $cred = GatewayCredential::resolveForPayment(null, 'cajupay');
        if ($cred === null || ! $cred->is_connected) {
            return self::defaults();
        }

        return self::fromCredentialsArray($cred->getDecryptedCredentials());
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{
     *     required_min_net: float,
     *     cajupay_payout_min_brl: float,
     *     cajupay_admin_fee_pix_brl: float,
     *     cajupay_admin_fee_payout_brl: float
     * }
     */
    public static function fromCredentialsArray(array $credentials): array
    {
        $e = GatewayPayoutEconomics::fromCredentialsArray('cajupay', $credentials);

        return [
            'required_min_net' => $e['required_min_net'],
            'cajupay_payout_min_brl' => $e['payout_min_brl'],
            'cajupay_admin_fee_pix_brl' => $e['admin_fee_pix_brl'],
            'cajupay_admin_fee_payout_brl' => $e['admin_fee_payout_brl'],
        ];
    }

    /**
     * @return array{
     *     required_min_net: float,
     *     cajupay_payout_min_brl: float,
     *     cajupay_admin_fee_pix_brl: float,
     *     cajupay_admin_fee_payout_brl: float
     * }
     */
    private static function defaults(): array
    {
        $e = GatewayPayoutEconomics::fromCredentialsArray('cajupay', []);

        return [
            'required_min_net' => $e['required_min_net'],
            'cajupay_payout_min_brl' => $e['payout_min_brl'],
            'cajupay_admin_fee_pix_brl' => $e['admin_fee_pix_brl'],
            'cajupay_admin_fee_payout_brl' => $e['admin_fee_payout_brl'],
        ];
    }
}
