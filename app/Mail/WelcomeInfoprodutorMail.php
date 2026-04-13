<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeInfoprodutorMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{app_name: string, theme_primary: string, logo_url: ?string}  $branding
     */
    public function __construct(
        public User $user,
        public array $branding,
        public string $dashboardUrl,
        public string $kycUrl
    ) {
        $this->subject('Bem-vindo(a) à '.$this->branding['app_name']);
    }

    public function build(): self
    {
        return $this->view('emails.welcome-infoprodutor', [
            'branding' => $this->branding,
            'recipientName' => $this->user->name,
            'dashboardUrl' => $this->dashboardUrl,
            'kycUrl' => $this->kycUrl,
        ]);
    }
}
