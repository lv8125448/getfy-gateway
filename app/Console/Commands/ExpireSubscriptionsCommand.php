<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;

class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:expire-due';

    protected $description = 'Marca assinaturas vencidas como past_due quando current_period_end < hoje.';

    public function handle(): int
    {
        $today = now()->startOfDay()->toDateString();

        $updated = Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('current_period_end')
            ->whereDate('current_period_end', '<', $today)
            ->whereHas('subscriptionPlan', fn ($q) => $q->where('interval', '!=', SubscriptionPlan::INTERVAL_LIFETIME))
            ->update(['status' => Subscription::STATUS_PAST_DUE]);

        $this->info("Assinaturas marcadas como past_due: {$updated}");

        return self::SUCCESS;
    }
}

