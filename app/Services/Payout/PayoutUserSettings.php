<?php

namespace App\Services\Payout;

/**
 * Leitura neutra dos dados de PIX para saque em users.payout_settings
 * (chaves globais da plataforma + compatibilidade com nomes legados).
 */
class PayoutUserSettings
{
    public static function pixKey(array $settings): string
    {
        foreach (['payout_pix_key', 'spacepag_pix_key', 'woovi_pix_key'] as $k) {
            $v = isset($settings[$k]) ? trim((string) $settings[$k]) : '';
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    public static function pixKeyType(array $settings): string
    {
        foreach (['payout_pix_key_type', 'spacepag_pix_key_type', 'woovi_pix_key_type'] as $k) {
            $v = isset($settings[$k]) ? trim((string) $settings[$k]) : '';
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    public static function pixLabel(array $settings): string
    {
        foreach (['payout_pix_label', 'cajupay_pix_label'] as $k) {
            $v = isset($settings[$k]) ? trim((string) $settings[$k]) : '';
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }
}
