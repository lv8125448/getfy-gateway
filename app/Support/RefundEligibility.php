<?php

namespace App\Support;

use App\Models\Order;
use App\Models\RefundRequest;

class RefundEligibility
{
    public static function canCustomerRequestRefund(Order $order): bool
    {
        if ($order->status !== 'completed') {
            return false;
        }
        $days = $order->product?->refund_policy_days;
        if ($days === null || (int) $days <= 0) {
            return false;
        }
        $deadline = $order->updated_at->copy()->addDays((int) $days);

        if (now()->gt($deadline)) {
            return false;
        }

        return ! RefundRequest::query()
            ->where('order_id', $order->id)
            ->where('status', RefundRequest::STATUS_PENDING)
            ->exists();
    }
}
