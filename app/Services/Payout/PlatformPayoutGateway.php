<?php

namespace App\Services\Payout;

use App\Models\GatewayCredential;
use App\Models\Setting;

/**
 * Provedor de payout da plataforma (saque automático PIX).
 *
 * Gateways com API de cashout: CajuPay, Spacepag e Woovi.
 * Preferência configurável em {@see Setting} `platform_payout_gateway` (`auto`, `cajupay`, `spacepag`, `woovi`).
 * Em `auto`, a ordem fixa é CajuPay → Spacepag → Woovi — o primeiro globalmente conectado vence.
 */
class PlatformPayoutGateway
{
    /** @var list<string> */
    public const PAYOUT_ORDER = ['cajupay', 'spacepag', 'woovi'];

    /**
     * Preferência salva no painel: automático ou forçar um dos três.
     *
     * @return 'auto'|'cajupay'|'spacepag'|'woovi'
     */
    public static function preference(): string
    {
        $v = Setting::get('platform_payout_gateway', null, null);
        if (in_array($v, ['cajupay', 'spacepag', 'woovi'], true)) {
            return $v;
        }

        return 'auto';
    }

    public static function activeSlug(): ?string
    {
        $connected = [];
        foreach (self::PAYOUT_ORDER as $slug) {
            $cred = GatewayCredential::resolveForPayment(null, $slug);
            if ($cred !== null && $cred->is_connected) {
                $connected[$slug] = true;
            }
        }

        if ($connected === []) {
            return null;
        }

        $pref = self::preference();

        if ($pref !== 'auto' && isset($connected[$pref])) {
            return $pref;
        }

        foreach (self::PAYOUT_ORDER as $slug) {
            if (isset($connected[$slug])) {
                return $slug;
            }
        }

        return null;
    }

    public static function isEnabled(): bool
    {
        return self::activeSlug() !== null;
    }
}
