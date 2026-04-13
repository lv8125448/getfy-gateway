<?php

namespace App\Mail;

use App\Models\Product;
use App\Models\ProductCoproducer;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CoproductionInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{app_name: string, theme_primary: string}  $branding
     */
    public function __construct(
        public ProductCoproducer $invitation,
        public Product $product,
        public User $inviter,
        public array $branding,
        public string $acceptUrl,
        public string $registerUrl,
    ) {
        $this->subject('Convite de co-produção: '.$product->name);
    }

    public function build(): self
    {
        return $this->view('emails.coproduction-invitation', [
            'branding' => $this->branding,
            'inviterName' => $this->inviter->name,
            'productName' => $this->product->name,
            'commissionPercent' => (float) $this->invitation->commission_percent,
            'acceptUrl' => $this->acceptUrl,
            'registerUrl' => $this->registerUrl,
            'recipientEmail' => $this->invitation->email,
        ]);
    }
}
