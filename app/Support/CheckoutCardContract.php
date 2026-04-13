<?php

namespace App\Support;

/**
 * Contrato de dados de cartão no checkout.
 * O frontend envia apenas payment_token (gerado pela lib JS Efí no browser).
 * Dados sensíveis (número, CVV, validade) nunca chegam ao backend.
 *
 * Payload esperado quando payment_method === 'card':
 * - payment_token: string (obrigatório) — token gerado pelo payment-token-efi no frontend
 * - card_mask: string (opcional) — máscara do cartão para exibição (ex: ****3335)
 */
final class CheckoutCardContract
{
    public const PAYMENT_TOKEN = 'payment_token';

    public const CARD_MASK = 'card_mask';

    /**
     * Campos que o backend aceita para cartão. Nenhum dado sensível.
     *
     * @return array<int, string>
     */
    public static function allowedKeys(): array
    {
        return [
            self::PAYMENT_TOKEN,
            self::CARD_MASK,
        ];
    }

    /**
     * Extrai e valida o array de cartão a partir do request (apenas chaves permitidas).
     *
     * @return array{payment_token: string, card_mask?: string}
     */
    public static function fromRequest(array $input): array
    {
        $card = [];
        foreach (self::allowedKeys() as $key) {
            if (isset($input[$key]) && is_string($input[$key]) && trim($input[$key]) !== '') {
                $card[$key] = trim($input[$key]);
            }
        }
        return $card;
    }
}
