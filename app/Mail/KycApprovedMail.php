<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class KycApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{app_name: string, theme_primary: string, logo_url: ?string}  $branding
     */
    public function __construct(
        public User $merchant,
        public array $branding,
        public string $dashboardUrl
    ) {
        $this->subject('Verificação aprovada — '.$this->branding['app_name']);
    }

    public function build(): self
    {
        return $this->view('emails.kyc-approved', [
            'branding' => $this->branding,
            'recipientName' => $this->merchant->name,
            'dashboardUrl' => $this->dashboardUrl,
        ]);
    }
}
