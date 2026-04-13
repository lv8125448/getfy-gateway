<?php

namespace App\Services;

use App\Events\OrderCompleted;
use App\Models\Order;
use App\Models\Subscription;
use InvalidArgumentException;

class OrderManualApprovalService
{
    /**
     * Marca o pedido como concluído (aprovação manual pela plataforma), concede acesso e dispara OrderCompleted.
     *
     * @throws InvalidArgumentException Se o pedido não estiver pendente
     */
    public static function approve(Order $order): void
    {
        if ($order->status !== 'pending') {
            throw new InvalidArgumentException('Só é possível aprovar pedidos com status pendente.');
        }

        $order->load(['product', 'productOffer', 'subscriptionPlan', 'orderItems.product']);

        $completedPatch = ['status' => 'completed', 'approved_manually' => true];
        if ($order->payment_method === null || $order->payment_method === '') {
            $meta = is_array($order->metadata) ? $order->metadata : [];
            $m = $meta['checkout_payment_method'] ?? null;
            $completedPatch['payment_method'] = in_array($m, ['pix', 'card', 'boleto', 'pix_auto'], true) ? $m : 'pix';
        }
        $order->update($completedPatch);
        $order->refresh();

        $order->grantPurchasedProductAccessToBuyer();

        if ($order->subscription_plan_id && $order->subscriptionPlan) {
            $plan = $order->subscriptionPlan;
            $exists = Subscription::where('user_id', $order->user_id)
                ->where('product_id', $order->product_id)
                ->where('subscription_plan_id', $plan->id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->exists();
            if (! $order->is_renewal && ! $exists) {
                [$periodStart, $periodEnd] = $plan->getCurrentPeriod();
                Subscription::create([
                    'tenant_id' => $order->tenant_id,
                    'user_id' => $order->user_id,
                    'product_id' => $order->product_id,
                    'subscription_plan_id' => $plan->id,
                    'status' => Subscription::STATUS_ACTIVE,
                    'current_period_start' => $periodStart,
                    'current_period_end' => $periodEnd,
                ]);
            }
        }

        event(new OrderCompleted($order));
    }
}
