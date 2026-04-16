<?php

namespace App\Mail;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RefundDecisionCustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RefundRequest $refundRequest,
        public bool $approved,
        public ?string $rejectionOrNote
    ) {
        $refundRequest->loadMissing('order');
        $orderRef = $refundRequest->order?->public_reference ?? (string) $refundRequest->order_id;
        $this->subject(
            $approved
                ? 'Reembolso aprovado — pedido #'.$orderRef
                : 'Atualização da sua solicitação de reembolso — pedido #'.$orderRef
        );
    }

    public function build(): self
    {
        $order = $this->refundRequest->order;
        $user = $this->refundRequest->user;
        $orderRef = $order?->public_reference ?? (string) $this->refundRequest->order_id;

        return $this->view('emails.refund-decision-customer', [
            'customerName' => $user?->name ?? 'Cliente',
            'orderRef' => $orderRef,
            'productName' => $order?->product?->name ?? 'Produto',
            'approved' => $this->approved,
            'reason' => $this->approved ? null : $this->rejectionOrNote,
            'note' => $this->approved ? $this->refundRequest->gateway_refund_note : null,
        ]);
    }
}
