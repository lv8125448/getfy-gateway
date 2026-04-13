<?php

namespace App\Services;

use App\Mail\KycApprovedMail;
use App\Mail\KycRejectedMail;
use App\Mail\KycSubmittedAdminMail;
use App\Mail\WelcomeInfoprodutorMail;
use App\Models\Setting;
use App\Models\User;
use App\Support\KycNotificationEmails;
use Illuminate\Support\Facades\Log;

class PlatformEmailNotifications
{
    public function __construct(
        protected PlatformTransactionalMailService $mail
    ) {}

    public function kycSubmitted(User $merchant): void
    {
        $raw = Setting::get('kyc_notification_emails', '', null);
        $emails = KycNotificationEmails::parse(is_string($raw) ? $raw : null);
        if ($emails === []) {
            if (config('app.debug')) {
                Log::debug('KYC: nenhum e-mail de alerta configurado (kyc_notification_emails).');
            }

            return;
        }

        $branding = BrandingEmailData::forTenant(null);
        $reviewUrl = route('plataforma.kyc.show', ['user' => $merchant->id]);

        $this->mail->send(
            new KycSubmittedAdminMail($merchant, $branding, $reviewUrl),
            $emails
        );
    }

    public function kycApproved(User $merchant): void
    {
        $branding = BrandingEmailData::forTenant($merchant->tenant_id);
        $dashboardUrl = url('/dashboard');

        $this->mail->send(
            new KycApprovedMail($merchant, $branding, $dashboardUrl),
            $merchant->email
        );
    }

    public function kycRejected(User $merchant, string $reason): void
    {
        $branding = BrandingEmailData::forTenant($merchant->tenant_id);
        $kycUrl = url('/financeiro?tab=seus-dados');

        $this->mail->send(
            new KycRejectedMail($merchant, $branding, $reason, $kycUrl),
            $merchant->email
        );
    }

    public function welcomeInfoprodutor(User $user): void
    {
        $branding = BrandingEmailData::forTenant($user->tenant_id);
        $dashboardUrl = url('/dashboard');
        $kycUrl = url('/financeiro?tab=seus-dados');

        $this->mail->send(
            new WelcomeInfoprodutorMail($user, $branding, $dashboardUrl, $kycUrl),
            $user->email
        );
    }
}
