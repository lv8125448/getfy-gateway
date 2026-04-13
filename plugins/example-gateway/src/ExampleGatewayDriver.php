<?php

namespace Plugins\ExampleGateway;

use App\Gateways\Contracts\GatewayDriver;

/**
 * Driver de exemplo para plugin de gateway.
 * Não processa pagamentos reais; serve apenas para validar o fluxo de registro e exibição na aba Gateways.
 */
class ExampleGatewayDriver implements GatewayDriver
{
    public function testConnection(array $credentials): bool
    {
        $key = trim($credentials['api_key'] ?? '');
        return $key !== '';
    }

    public function createPixPayment(
        array $credentials,
        float $amount,
        array $consumer,
        string $externalId,
        string $postbackUrl
    ): array {
        throw new \RuntimeException('Example Gateway: não processa pagamentos reais. Use um gateway real em produção.');
    }

    public function getTransactionStatus(string $transactionId, array $credentials): ?string
    {
        return null;
    }

    public function createCardPayment(
        array $credentials,
        float $amount,
        array $consumer,
        string $externalId,
        array $card
    ): array {
        throw new \RuntimeException('Example Gateway: não processa pagamentos reais. Use um gateway real em produção.');
    }

    public function createBoletoPayment(
        array $credentials,
        float $amount,
        array $consumer,
        string $externalId,
        string $notificationUrl
    ): array {
        throw new \RuntimeException('Example Gateway: não processa pagamentos reais. Use um gateway real em produção.');
    }
}
