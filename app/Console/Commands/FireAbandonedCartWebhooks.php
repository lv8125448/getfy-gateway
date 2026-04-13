<?php

namespace App\Console\Commands;

use App\Events\CartAbandoned;
use App\Models\CheckoutSession;
use Illuminate\Console\Command;

class FireAbandonedCartWebhooks extends Command
{
    protected $signature = 'checkout:fire-abandoned-cart-webhooks
                            {--minutes=10 : Idade mínima em minutos para considerar abandonado}
                            {--tenant= : Filtrar por tenant_id (opcional)}';

    protected $description = 'Dispara eventos CartAbandoned para sessões de checkout não convertidas (form_started ou form_filled).';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;

        $query = CheckoutSession::query()
            ->whereIn('step', [CheckoutSession::STEP_FORM_STARTED, CheckoutSession::STEP_FORM_FILLED])
            ->whereNull('abandoned_webhook_fired_at')
            ->where('updated_at', '<=', now()->subMinutes($minutes))
            ->where(function ($q) {
                $q->whereNull('order_id')
                    ->orWhereDoesntHave('order', fn ($orderQuery) => $orderQuery->where('status', 'completed'));
            });

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $sessions = $query->with('product')->get();
        $count = 0;

        foreach ($sessions as $session) {
            if ($session->tenant_id === null) {
                continue;
            }
            event(new CartAbandoned($session));
            $session->update(['abandoned_webhook_fired_at' => now()]);
            $count++;
        }

        $this->info("CartAbandoned disparado para {$count} sessão(ões).");

        return self::SUCCESS;
    }
}
