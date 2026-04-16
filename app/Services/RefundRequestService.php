<?php

namespace App\Services;

use App\Mail\RefundDecisionCustomerMail;
use App\Mail\RefundRequestSellerMail;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\PlatformOrderAdminService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RefundRequestService
{
    public function __construct(
        protected OrderRefundGatewayBridge $gatewayBridge
    ) {}

    public function createFromCustomer(Order $order, User $customer, string $reason): RefundRequest
    {
        return RefundRequest::create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'tenant_id' => (int) $order->tenant_id,
            'status' => RefundRequest::STATUS_PENDING,
            'customer_reason' => $reason,
        ]);
    }

    public function notifySeller(RefundRequest $request): void
    {
        $owner = User::query()->find($request->tenant_id);
        if (! $owner || ! $owner->email) {
            Log::warning('RefundRequestService: titular do tenant sem e-mail.', ['tenant_id' => $request->tenant_id]);

            return;
        }

        $url = url('/reembolsos');
        Mail::to($owner->email)->send(new RefundRequestSellerMail($request->fresh(['order.product']), $url));
    }

    public function approve(User $seller, RefundRequest $request): void
    {
        if ($request->status !== RefundRequest::STATUS_PENDING) {
            throw new \InvalidArgumentException('Solicitação não está pendente.');
        }
        $order = $request->order;
        if (! $order || (int) $order->tenant_id !== (int) $seller->tenant_id) {
            throw new \InvalidArgumentException('Pedido inválido.');
        }

        $gw = $this->gatewayBridge->tryRefund($order);
        $request->update([
            'gateway_refund_status' => $gw['status'],
            'gateway_refund_note' => $gw['note'],
        ]);

        try {
            PlatformOrderAdminService::refundPaidOrDisputed($order->fresh());
        } catch (\Throwable $e) {
            Log::error('RefundRequestService: falha ao estornar carteira.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $request->update([
            'status' => RefundRequest::STATUS_APPROVED,
            'resolved_by_user_id' => $seller->id,
            'resolved_at' => now(),
        ]);

        $customer = $request->user;
        if ($customer && $customer->email) {
            Mail::to($customer->email)->send(new RefundDecisionCustomerMail($request->fresh(['order.product']), true, null));
        }
    }

    public function reject(User $seller, RefundRequest $request, ?string $reason): void
    {
        if ($request->status !== RefundRequest::STATUS_PENDING) {
            throw new \InvalidArgumentException('Solicitação não está pendente.');
        }
        $order = $request->order;
        if (! $order || (int) $order->tenant_id !== (int) $seller->tenant_id) {
            throw new \InvalidArgumentException('Pedido inválido.');
        }

        $request->update([
            'status' => RefundRequest::STATUS_REJECTED,
            'seller_rejection_reason' => $reason,
            'resolved_by_user_id' => $seller->id,
            'resolved_at' => now(),
        ]);

        $customer = $request->user;
        if ($customer && $customer->email) {
            Mail::to($customer->email)->send(new RefundDecisionCustomerMail($request->fresh(['order.product']), false, $reason));
        }
    }
}
