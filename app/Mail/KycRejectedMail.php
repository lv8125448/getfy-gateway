<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class KycRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{app_name: string, theme_primary: string, logo_url: ?string}  $branding
     */
    public function __construct(
        public User $merchant,
        public array $branding,
        public string $rejectionReason,
        public string $kycUrl
    ) {
        $this->subject('Verificação não aprovada — '.$this->branding['app_name']);
    }

    public function build(): self
    {
        return $this->view('emails.kyc-rejected', [
            'branding' => $this->branding,
            'recipientName' => $this->merchant->name,
            'rejectionReason' => $this->rejectionReason,
            'kycUrl' => $this->kycUrl,
        ]);
    }
}
