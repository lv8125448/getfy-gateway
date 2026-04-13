<?php

namespace App\Jobs;

use App\Mail\SubscriptionReminderMail;
use App\Models\Subscription;
use App\Services\TenantMailConfigService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TenantMailConfigService $mailConfig): void
    {
        $today = Carbon::today();
        $inThreeDays = $today->copy()->addDays(3);
        $inOneDay = $today->copy()->addDay();

        $subscriptions = Subscription::with(['user', 'product', 'subscriptionPlan'])
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('current_period_end')
            ->whereIn('current_period_end', [$inThreeDays->toDateString(), $inOneDay->toDateString()])
            ->get();

        foreach ($subscriptions as $subscription) {
            if ($subscription->subscriptionPlan && $subscription->subscriptionPlan->isLifetime()) {
                continue;
            }
            $user = $subscription->user;
            if (! $user || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $renewalUrl = url('/renovar/' . $subscription->renewal_token);
            $daysLeft = (int) $today->diffInDays(Carbon::parse($subscription->current_period_end), false);
            if ($daysLeft <= 0) {
                continue;
            }
            $subject = 'Lembrete: sua assinatura de ' . $subscription->product->name . ' renova em ' . $daysLeft . ' dia(s)';
            $body = '<p>Olá' . ($user->name ? ', ' . e($user->name) : '') . '!</p>';
            $body .= '<p>Sua assinatura de <strong>' . e($subscription->product->name) . '</strong> (plano ' . e($subscription->subscriptionPlan->name) . ') renova em <strong>' . $daysLeft . ' dia(s)</strong>.</p>';
            $body .= '<p>Para renovar e manter seu acesso, use o link abaixo:</p>';
            $body .= '<p><a href="' . e($renewalUrl) . '" style="display:inline-block;padding:12px 24px;background:#0ea5e9;color:#fff;text-decoration:none;border-radius:8px;">Renovar agora</a></p>';
            $body .= '<p>Ou copie e cole no navegador: ' . e($renewalUrl) . '</p>';

            try {
                $mailConfig->applyMailerConfigForTenant($subscription->tenant_id, [], null);
                Mail::mailer('smtp')->to($user->email)->send(new SubscriptionReminderMail($subject, $body));
            } catch (\Throwable $e) {
                Log::warning('SendSubscriptionRemindersJob: falha ao enviar lembrete.', [
                    'subscription_id' => $subscription->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
