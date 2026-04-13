<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\SpedyIntegration;
use App\Services\SpedyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SpedyIssueInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $spedyIntegrationId,
        public int $orderId
    ) {}

    public function handle(SpedyService $spedyService): void
    {
        try {
            $integration = SpedyIntegration::with('products:id')
                ->find($this->spedyIntegrationId);

            if (! $integration || ! $integration->is_active || ! $integration->api_key) {
                return;
            }

            $order = Order::with(['user', 'product', 'orderItems.product', 'orderItems.productOffer', 'orderItems.subscriptionPlan'])
                ->find($this->orderId);

            if (! $order) {
                return;
            }

            if (! $integration->appliesToProduct($order->product_id ? (string) $order->product_id : null)) {
                return;
            }

            $spedyService->createOrderAndIssueInvoices(
                $order,
                $integration->api_key,
                $integration->environment ?? SpedyIntegration::ENVIRONMENT_PRODUCTION
            );
        } catch (\Throwable $e) {
            Log::warning('SpedyIssueInvoiceJob failed', [
                'order_id' => $this->orderId,
                'spedy_integration_id' => $this->spedyIntegrationId,
                'message' => $e->getMessage(),
            ]);
            // Não relançar: evita FAIL na fila; o erro já foi registrado em log
        }
    }
}
