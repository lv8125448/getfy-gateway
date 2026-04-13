<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Jobs\SpedyIssueInvoiceJob;
use App\Models\SpedyIntegration;
use Illuminate\Contracts\Events\Dispatcher;

class SpedyEventSubscriber
{
    /**
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            OrderCompleted::class => 'handleOrderCompleted',
        ];
    }

    public function handleOrderCompleted(OrderCompleted $event): void
    {
        $order = $event->order;
        $tenantId = $order->tenant_id;
        $productId = $order->product_id !== null ? (string) $order->product_id : null;

        $integrations = SpedyIntegration::forTenant($tenantId)
            ->where('is_active', true)
            ->with('products:id')
            ->get();

        foreach ($integrations as $integration) {
            if (! $integration->api_key) {
                continue;
            }
            if (! $integration->appliesToProduct($productId)) {
                continue;
            }

            SpedyIssueInvoiceJob::dispatch($integration->id, $order->id);
        }
    }
}
