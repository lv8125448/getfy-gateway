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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TenantMailConfigService $mailConfig): void
    {
        $today = Carbon::today();
        $windowStart = $today->copy()->subDays(2);
        $windowEnd = $today->copy()->addDays(3);

        $subscriptions = Subscription::with(['user', 'product', 'subscriptionPlan'])
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE])
            ->whereNotNull('current_period_end')
            ->whereBetween('current_period_end', [$windowStart->toDateString(), $windowEnd->toDateString()])
            ->get();

        foreach ($subscriptions as $subscription) {
            if (! $subscription->subscriptionPlan || $subscription->subscriptionPlan->isLifetime()) {
                continue;
            }
            $user = $subscription->user;
            if (! $user || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $periodEnd = Carbon::parse($subscription->current_period_end)->startOfDay();
            $daysLeft = (int) $today->diffInDays($periodEnd, false);
            if (! in_array($daysLeft, [3, 0, -1, -2], true)) {
                continue;
            }

            $stage = match ($daysLeft) {
                3 => 'd-3',
                0 => 'd0',
                -1 => 'd+1',
                -2 => 'd+2',
                default => null,
            };
            if ($stage === null) {
                continue;
            }

            $idempotencyKey = sprintf(
                'subscription_reminder:%s:%s:%s:%s',
                $subscription->id,
                $periodEnd->toDateString(),
                $stage,
                $today->toDateString()
            );
            if (! Cache::add($idempotencyKey, 1, now()->addDays(5))) {
                continue;
            }

            $renewalUrl = url('/renovar/' . $subscription->renewal_token);
            $planName = e($subscription->subscriptionPlan->name);
            $productName = e($subscription->product->name);
            $greeting = '<p>Olá' . ($user->name ? ', ' . e($user->name) : '') . '!</p>';

            if ($daysLeft > 0) {
                $subject = 'Lembrete: sua assinatura de ' . $subscription->product->name . ' vence em ' . $daysLeft . ' dia(s)';
                $headline = '<p>Sua assinatura de <strong>' . $productName . '</strong> (plano ' . $planName . ') vence em <strong>' . $daysLeft . ' dia(s)</strong>.</p>';
            } elseif ($daysLeft === 0) {
                $subject = 'Atenção: sua assinatura de ' . $subscription->product->name . ' vence hoje';
                $headline = '<p>Sua assinatura de <strong>' . $productName . '</strong> (plano ' . $planName . ') <strong>vence hoje</strong>.</p>';
            } else {
                $daysOverdue = abs($daysLeft);
                $subject = 'Sua assinatura de ' . $subscription->product->name . ' está vencida';
                $headline = '<p>Sua assinatura de <strong>' . $productName . '</strong> (plano ' . $planName . ') está vencida há <strong>' . $daysOverdue . ' dia(s)</strong>.</p>';
            }

            $body = '<p>Olá' . ($user->name ? ', ' . e($user->name) : '') . '!</p>';
            $body = $greeting;
            $body .= $headline;
            $body .= '<p>Para renovar e manter seu acesso, use o link abaixo:</p>';
            $body .= '<p><a href="' . e($renewalUrl) . '" style="display:inline-block;padding:12px 24px;background:#0ea5e9;color:#fff;text-decoration:none;border-radius:8px;">Renovar agora</a></p>';
            $body .= '<p>Ou copie e cole no navegador: ' . e($renewalUrl) . '</p>';

            try {
                $mailConfig->applyMailerConfigForTenant($subscription->tenant_id, [], null);
                Mail::mailer('smtp')->to($user->email)->send(new SubscriptionReminderMail($subject, $body));
            } catch (\Throwable $e) {
                Cache::forget($idempotencyKey);
                Log::warning('SendSubscriptionRemindersJob: falha ao enviar lembrete.', [
                    'subscription_id' => $subscription->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
