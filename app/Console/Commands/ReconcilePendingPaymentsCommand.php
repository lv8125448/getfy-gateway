<?php

namespace App\Console\Commands;

use App\Gateways\GatewayRegistry;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\GatewayCredential;
use App\Models\Order;
use Illuminate\Console\Command;

class ReconcilePendingPaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile-pending
                            {--limit=200 : Máximo de pedidos para checar por execução}
                            {--days=30 : Considerar pedidos criados nos últimos X dias}
                            {--min-age-minutes=2 : Não checar pedidos atualizados muito recentemente}';

    protected $description = 'Reconfirma pagamentos pendentes no gateway e aprova automaticamente quando liquidado.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $days = max(1, (int) $this->option('days'));
        $minAgeMinutes = max(0, (int) $this->option('min-age-minutes'));

        $query = Order::query()
            ->where('status', 'pending')
            ->whereNotNull('gateway')
            ->where('gateway', '!=', '')
            ->whereNotNull('gateway_id')
            ->where('gateway_id', '!=', '')
            ->where('created_at', '>=', now()->subDays($days));

        if ($minAgeMinutes > 0) {
            $query->where('updated_at', '<=', now()->subMinutes($minAgeMinutes));
        }

        $orders = $query
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        $checked = 0;
        $paid = 0;
        $cancelled = 0;

        foreach ($orders as $order) {
            $checked++;

            $gatewaySlug = is_string($order->gateway) ? $order->gateway : '';
            $transactionId = is_string($order->gateway_id) ? $order->gateway_id : (string) $order->gateway_id;

            if ($gatewaySlug === '' || $transactionId === '') {
                continue;
            }

            $credential = GatewayCredential::resolveForPayment($order->tenant_id, $gatewaySlug);

            if (! $credential) {
                continue;
            }

            $driver = GatewayRegistry::driver($gatewaySlug);
            if (! $driver) {
                continue;
            }

            $credentials = $credential->getDecryptedCredentials();
            if ($credentials === []) {
                continue;
            }

            try {
                $apiStatus = $driver->getTransactionStatus($transactionId, $credentials);
            } catch (\Throwable) {
                $apiStatus = null;
            }

            if ($apiStatus === 'paid') {
                ProcessPaymentWebhook::dispatchSync($gatewaySlug, $transactionId, 'order.paid', 'paid', [
                    'source' => 'reconcile_pending',
                ]);
                $paid++;
                continue;
            }

            if ($apiStatus === 'cancelled') {
                ProcessPaymentWebhook::dispatchSync($gatewaySlug, $transactionId, 'order.cancelled', 'cancelled', [
                    'source' => 'reconcile_pending',
                ]);
                $cancelled++;
                continue;
            }
        }

        $this->info("Checados: {$checked} | Pagos: {$paid} | Cancelados: {$cancelled}");

        return self::SUCCESS;
    }
}

