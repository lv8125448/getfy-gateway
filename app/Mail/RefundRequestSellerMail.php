<?php

namespace App\Mail;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RefundRequestSellerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RefundRequest $refundRequest,
        public string $manageUrl
    ) {
        $refundRequest->loadMissing('order');
        $orderRef = $refundRequest->order?->public_reference ?? (string) $refundRequest->order_id;
        $this->subject('Nova solicitação de reembolso — pedido #'.$orderRef);
    }

    public function build(): self
    {
        $order = $this->refundRequest->order;
        $productName = $order?->product?->name ?? 'Produto';
        $orderRef = $order?->public_reference ?? (string) $this->refundRequest->order_id;

        return $this->view('emails.refund-request-seller', [
            'orderRef' => $orderRef,
            'productName' => $productName,
            'reason' => $this->refundRequest->customer_reason,
            'manageUrl' => $this->manageUrl,
        ]);
    }
}
