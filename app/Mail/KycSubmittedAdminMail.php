<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class KycSubmittedAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{app_name: string, theme_primary: string, logo_url: ?string}  $branding
     */
    public function __construct(
        public User $merchant,
        public array $branding,
        public string $reviewUrl
    ) {
        $this->subject('Novo pedido de verificação KYC — '.$this->branding['app_name']);
    }

    public function build(): self
    {
        return $this->view('emails.kyc-submitted-admin', [
            'branding' => $this->branding,
            'merchantName' => $this->merchant->name,
            'merchantEmail' => $this->merchant->email,
            'reviewUrl' => $this->reviewUrl,
        ]);
    }
}
