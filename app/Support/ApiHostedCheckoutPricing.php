<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\SubscriptionPlan;

/**
 * Alinha o valor cobrado em sessões de checkout hospedado (API) ao cálculo de pedidos na API de pagamentos.
 */
class ApiHostedCheckoutPricing
{
    public static function expectedAmountBrl(
        int $tenantId,
        string $productId,
        ?int $productOfferId,
        ?int $subscriptionPlanId,
    ): ?float {
        $product = Product::where('id', $productId)->where('tenant_id', $tenantId)->first();
        if (! $product) {
            return null;
        }

        $orderAmount = (float) $product->price;
        $currency = $product->currency ?? 'BRL';

        if ($productOfferId) {
            $offer = ProductOffer::where('id', $productOfferId)->where('product_id', $product->id)->first();
            if (! $offer) {
                return null;
            }
            $orderAmount = (float) $offer->price;
            $currency = $offer->getCurrencyOrDefault();
        } elseif ($subscriptionPlanId) {
            $plan = SubscriptionPlan::where('id', $subscriptionPlanId)->where('product_id', $product->id)->first();
            if (! $plan) {
                return null;
            }
            $orderAmount = (float) $plan->price;
            $currency = $plan->getCurrencyOrDefault();
        }

        $rates = config('products.rates', ['brl_eur' => 0.16, 'brl_usd' => 0.18]);
        if ($currency !== 'BRL') {
            $orderAmount = $currency === 'EUR' ? $orderAmount / ($rates['brl_eur'] ?? 0.16) : $orderAmount / ($rates['brl_usd'] ?? 0.18);
        }

        return $orderAmount;
    }

    public static function amountToBrl(float $amount, string $currency): float
    {
        $currency = strtoupper($currency);
        if ($currency === 'BRL') {
            return $amount;
        }
        $rates = config('products.rates', ['brl_eur' => 0.16, 'brl_usd' => 0.18]);

        return $currency === 'EUR'
            ? $amount / ($rates['brl_eur'] ?? 0.16)
            : $amount / ($rates['brl_usd'] ?? 0.18);
    }
}
