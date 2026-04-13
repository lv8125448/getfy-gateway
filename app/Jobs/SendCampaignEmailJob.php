<?php

namespace App\Jobs;

use App\Mail\CampaignMail;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignSend;
use App\Services\TenantMailConfigService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCampaignEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $emailCampaignId,
        public string $email,
        public ?int $userId,
        public string $name
    ) {}

    public function handle(TenantMailConfigService $mailConfig): void
    {
        $campaign = EmailCampaign::find($this->emailCampaignId);
        if (! $campaign || ! $campaign->isSending()) {
            return;
        }

        $mailConfig->applyMailerConfigForTenant($campaign->tenant_id, [], null);

        $body = str_replace(
            ['{nome}', '{email}'],
            [e($this->name), e($this->email)],
            $campaign->body_html
        );

        try {
            Mail::mailer('smtp')->to($this->email)->send(new CampaignMail($campaign->subject, $body));
        } catch (\Throwable $e) {
            Log::warning('SendCampaignEmailJob: falha ao enviar.', [
                'campaign_id' => $this->emailCampaignId,
                'email' => $this->email,
                'message' => $e->getMessage(),
            ]);
            return;
        }

        EmailCampaignSend::create([
            'email_campaign_id' => $campaign->id,
            'user_id' => $this->userId,
            'email' => $this->email,
            'sent_at' => now(),
        ]);

        $campaign->increment('sent_count');
    }
}
