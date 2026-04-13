<?php

namespace App\Console\Commands;

use App\Jobs\SendCampaignEmailJob;
use App\Models\EmailCampaign;
use App\Services\EmailCampaignRecipientsService;
use Illuminate\Console\Command;

class ProcessEmailCampaignsCommand extends Command
{
    protected $signature = 'email-campaign:process';

    protected $description = 'Processa campanhas de e-mail em envio (até 30 destinatários por campanha por minuto).';

    public function handle(EmailCampaignRecipientsService $recipientsService): int
    {
        $campaigns = EmailCampaign::sending()->get();

        foreach ($campaigns as $campaign) {
            $recipients = $recipientsService->getNextRecipientsForCampaign($campaign, 30);

            if ($recipients->isEmpty()) {
                $campaign->update([
                    'status' => EmailCampaign::STATUS_SENT,
                    'sent_at' => now(),
                ]);
                continue;
            }

            foreach ($recipients as $r) {
                SendCampaignEmailJob::dispatch(
                    $campaign->id,
                    $r['email'],
                    $r['user_id'] ?? null,
                    $r['name'] ?? $r['email']
                );
            }
        }

        return self::SUCCESS;
    }
}
