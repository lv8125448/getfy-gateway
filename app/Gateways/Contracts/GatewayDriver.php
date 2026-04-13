<?php

namespace App\Gateways\Contracts;

interface GatewayDriver
{
    /**
     * Test connection with the given credentials.
     *
     * @param  array<string, string>  $credentials
     */
    public function testConnection(array $credentials): bool;

    /**
     * Create a PIX payment. Returns transaction data for the frontend.
     *
     * @param  array<string, string>  $credentials
     * @param  array{name: string, document: string, email: string}  $consumer
     * @return array{transaction_id: string, qrcode?: string, copy_paste?: string, raw?: array}
     */
    public function createPixPayment(
        array $credentials,
        float $amount,
        array $consumer,
        string $externalId,
        string $postbackUrl
    ): array;

    /**
     * Consulta o status real da transação na API do gateway (reconfirmação).
     * Retorna 'paid', 'pending', 'cancelled' ou null em caso de erro/transação não encontrada.
     *
     * @param  array<string, string>  $credentials
     */
    public function getTransactionStatus(string $transactionId, array $credentials): ?string;

    /**
     * Cria pagamento com cartão de crédito. Opcional: só gateways que suportam cartão implementam.
     *
     * @param  array<string, string>  $credentials
     * @param  array{name: string, document: string, email: string}  $consumer
     * @param  array{payment_token: string, card_mask?: string}  $card
     * @return array{transaction_id: string, status?: string}
     */
    public function createCardPayment(
        array $credentials,
        float $amount,
        array $consumer,
        string $externalId,
        array $card
    ): array;

    /**
     * Cria pagamento por boleto. Opcional: só gateways que suportam boleto implementam.
     *
     * @param  array<string, string>  $credentials
     * @param  array{name: string, document: string, email: string}  $consumer
     * @return array{transaction_id: string, amount: float, expire_at: string, barcode: string, pdf_url: string, raw?: array}
     */
    public function createBoletoPayment(
        array $credentials,
        float $amount,
        array $consumer,
        string $externalId,
        string $notificationUrl
    ): array;
}
