<?php

namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * E-mails transacionais da plataforma (SMTP global em settings, tenant_id null).
 */
class PlatformTransactionalMailService
{
    public function __construct(
        protected TenantMailConfigService $mailConfig
    ) {}

    /**
     * @param  string|list<string>  $to
     */
    public function send(Mailable $mailable, string|array $to): void
    {
        $recipients = is_array($to) ? array_values(array_filter($to)) : [trim($to)];
        if ($recipients === []) {
            return;
        }

        $this->mailConfig->applyMailerConfigForTenant(null);
        config(['mail.default' => 'smtp']);
        Mail::purge('smtp');

        try {
            Mail::mailer('smtp')->to($recipients)->send($mailable);
        } catch (\Throwable $e) {
            Log::warning('PlatformTransactionalMailService: falha ao enviar e-mail.', [
                'to' => $recipients,
                'mailable' => $mailable::class,
                'message' => $e->getMessage(),
            ]);
            report($e);
        }
    }
}
