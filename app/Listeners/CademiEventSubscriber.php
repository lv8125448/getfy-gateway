<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Jobs\CademiGrantAccessJob;
use App\Models\CademiIntegration;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CademiEventSubscriber
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
        $order->loadMissing(['product', 'productOffer', 'subscriptionPlan']);

        Log::info('CademiEventSubscriber: OrderCompleted recebido', [
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
            'product_id' => $order->product_id,
            'product_offer_id' => $order->product_offer_id,
            'subscription_plan_id' => $order->subscription_plan_id,
            'product_type' => $order->product?->type,
        ]);

        $tenantId = $order->tenant_id;
        $integrations = CademiIntegration::forTenant($tenantId)
            ->where('is_active', true)
            ->get();

        if ($integrations->isEmpty()) {
            Log::info('CademiEventSubscriber: nenhuma integração ativa/configurada', [
                'tenant_id' => $tenantId,
                'order_id' => $order->id,
            ]);
            return;
        }

        foreach ($integrations as $integration) {
            $mapping = $integration->resolveMappingForOrder($order);
            if ($mapping === null) {
                Log::info('CademiEventSubscriber: sem mapping para integração', [
                    'order_id' => $order->id,
                    'integration_id' => $integration->id,
                ]);
                continue;
            }

            $tagId = isset($mapping['tag_id']) ? (int) $mapping['tag_id'] : 0;
            $produtoId = isset($mapping['produto_id']) ? (int) $mapping['produto_id'] : 0;
            $produtoIds = isset($mapping['produto_ids']) && is_array($mapping['produto_ids'])
                ? array_values(array_unique(array_filter(array_map('intval', $mapping['produto_ids']), fn ($v) => $v > 0)))
                : [];
            if ($produtoIds === [] && $produtoId > 0) {
                $produtoIds = [$produtoId];
            }

            if ($integration->isTagsApi() && $tagId <= 0) {
                Log::info('CademiEventSubscriber: mapping sem tag_id (ignorando)', [
                    'order_id' => $order->id,
                    'integration_id' => $integration->id,
                    'delivery_method' => $integration->delivery_method,
                    'mapping_scope' => $mapping['scope'] ?? null,
                    'produto_id' => $mapping['produto_id'] ?? null,
                ]);
                continue;
            }

            if ($integration->isPostbackCustom() && $produtoIds === []) {
                Log::info('CademiEventSubscriber: mapping sem produto_id (ignorando)', [
                    'order_id' => $order->id,
                    'integration_id' => $integration->id,
                    'delivery_method' => $integration->delivery_method,
                    'mapping_scope' => $mapping['scope'] ?? null,
                    'tag_id' => $mapping['tag_id'] ?? null,
                ]);
                continue;
            }

            // Avoid duplicate dispatch if already processed for this order/integration/tag.
            $meta = is_array($order->metadata ?? null) ? $order->metadata : [];
            if ($integration->isPostbackCustom()) {
                $allDone = true;
                foreach ($produtoIds as $pid) {
                    $k = 'cademi:' . $integration->id . ':postback:produto:' . $pid;
                    if (! (isset($meta[$k]['status']) && in_array($meta[$k]['status'], ['ok', 'skipped'], true))) {
                        $allDone = false;
                        break;
                    }
                }
                if ($allDone) {
                    Log::info('CademiEventSubscriber: já processado (skip)', [
                        'order_id' => $order->id,
                        'integration_id' => $integration->id,
                        'delivery_method' => $integration->delivery_method,
                        'tag_id' => $tagId ?: null,
                        'produto_ids' => $produtoIds,
                    ]);
                    continue;
                }
            } else {
                $metaKey = 'cademi:' . $integration->id . ':tag:' . $tagId;
                if (isset($meta[$metaKey]['status']) && in_array($meta[$metaKey]['status'], ['ok', 'skipped'], true)) {
                    Log::info('CademiEventSubscriber: já processado (skip)', [
                        'order_id' => $order->id,
                        'integration_id' => $integration->id,
                        'delivery_method' => $integration->delivery_method,
                        'tag_id' => $tagId ?: null,
                        'status' => $meta[$metaKey]['status'] ?? null,
                    ]);
                    continue;
                }
            }

            if ($this->shouldDispatchSync()) {
                Log::info('CademiEventSubscriber: dispatchSync', [
                    'order_id' => $order->id,
                    'integration_id' => $integration->id,
                    'delivery_method' => $integration->delivery_method,
                    'tag_id' => $tagId ?: null,
                    'produto_id' => $produtoId ?: null,
                    'mapping_scope' => $mapping['scope'] ?? null,
                ]);
                CademiGrantAccessJob::dispatchSync($integration->id, (int) $order->id);
            } else {
                Log::info('CademiEventSubscriber: dispatch async', [
                    'order_id' => $order->id,
                    'integration_id' => $integration->id,
                    'delivery_method' => $integration->delivery_method,
                    'tag_id' => $tagId ?: null,
                    'produto_id' => $produtoId ?: null,
                    'mapping_scope' => $mapping['scope'] ?? null,
                ]);
                CademiGrantAccessJob::dispatch($integration->id, (int) $order->id);
            }
        }
    }

    private function shouldDispatchSync(): bool
    {
        $default = (string) config('queue.default', 'sync');
        if ($default === 'sync' || $default === 'database') {
            return true;
        }

        $v = (string) env('INTEGRATIONS_DISPATCH_SYNC', '');
        if ($v !== '' && in_array(Str::lower(trim($v)), ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        return false;
    }
}

