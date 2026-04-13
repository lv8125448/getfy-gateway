<?php

namespace App\Services;

use App\Models\PanelNotification;
use App\Models\PanelPushSubscription;
use App\Support\VapidEnvKeys;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

class PanelPushService
{
    /**
     * Envia push para o tenant e persiste uma notificação por usuário (para o centro de notificações).
     *
     * @param  string  $type  Tipo para o centro de notificações: sale_approved, pix_generated, boleto_generated, etc.
     * @param  string|null  $eventKey  Chave única do evento (ex: order_123). Quando informada, evita duplicar notificação para o mesmo evento.
     */
    public function sendAndPersistToTenant(?int $tenantId, string $type, string $title, string $body, ?string $url = null, ?string $eventKey = null): int
    {
        $subscriptions = PanelPushSubscription::where('tenant_id', $tenantId)->get();
        $userIds = $subscriptions->pluck('user_id')->unique()->filter()->values();
        $anyNewNotification = false;

        foreach ($userIds as $userId) {
            $attrs = [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'url' => $url,
            ];
            if ($eventKey !== null && $eventKey !== '') {
                $notification = PanelNotification::firstOrCreate(
                    [
                        'user_id' => $userId,
                        'event_key' => $eventKey,
                    ],
                    array_merge($attrs, ['event_key' => $eventKey])
                );
                if ($notification->wasRecentlyCreated) {
                    $anyNewNotification = true;
                }
            } else {
                PanelNotification::create($attrs);
                $anyNewNotification = true;
            }
        }

        if ($eventKey !== null && $eventKey !== '' && ! $anyNewNotification) {
            return 0;
        }

        $result = $this->sendToSubscriptions($subscriptions, $title, $body, $url);

        return (int) ($result['sent'] ?? 0);
    }

    /**
     * Envia push global para todos os assinantes do painel e persiste no centro de notificações.
     *
     * @return array{sent:int,failed:int,invalid:int,expired:int,total:int}
     */
    public function sendAndPersistToAll(string $type, string $title, string $body, ?string $url = null): array
    {
        $subscriptions = PanelPushSubscription::query()->get();
        $userIds = $subscriptions->pluck('user_id')->unique()->filter()->values();
        foreach ($userIds as $userId) {
            PanelNotification::create([
                'tenant_id' => null,
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'url' => $url,
            ]);
        }

        return $this->sendToSubscriptions($subscriptions, $title, $body, $url);
    }

    public function sendToTenant(?int $tenantId, string $title, string $body, ?string $url = null): int
    {
        $subscriptions = PanelPushSubscription::where('tenant_id', $tenantId)->get();
        if ($subscriptions->isEmpty()) {
            Log::warning('PanelPushService: nenhuma inscrição push para o tenant (usuário deve permitir notificações no painel)', ['tenant_id' => $tenantId]);
            return 0;
        }

        $result = $this->sendToSubscriptions($subscriptions, $title, $body, $url);

        return (int) ($result['sent'] ?? 0);
    }

    /**
     * @param Collection<int, PanelPushSubscription> $subscriptions
     * @return array{sent:int,failed:int,invalid:int,expired:int,total:int}
     */
    private function sendToSubscriptions(Collection $subscriptions, string $title, string $body, ?string $url = null): array
    {
        $vapidPublic = VapidEnvKeys::normalize(config('getfy.pwa.vapid_public'));
        $vapidPrivate = VapidEnvKeys::normalize(config('getfy.pwa.vapid_private'));

        if (! $vapidPublic || ! $vapidPrivate) {
            Log::warning('PanelPushService: VAPID não configurado (defina PWA_VAPID_PUBLIC e PWA_VAPID_PRIVATE no .env)');
            return ['sent' => 0, 'failed' => 0, 'invalid' => 0, 'expired' => 0, 'total' => $subscriptions->count()];
        }

        $subject = 'mailto:' . (config('mail.from.address') ?: 'noreply@' . parse_url(config('app.url'), PHP_URL_HOST));

        try {
            VAPID::validate([
                'subject' => $subject,
                'publicKey' => $vapidPublic,
                'privateKey' => $vapidPrivate,
            ]);
        } catch (\Throwable $e) {
            Log::error('PanelPushService: par VAPID rejeitado pela lib web-push (chave truncada/corrompida ou subject inválido).', [
                'message' => $e->getMessage(),
                'public_b64url_len' => strlen($vapidPublic),
                'private_b64url_len' => strlen($vapidPrivate),
                'hint' => 'Rode `php artisan pwa:vapid` no container app; confira uma única linha PWA_VAPID_* no .env e em .docker/pwa_vapid.env; reinicie app+queue; reative notificações no PWA após trocar o par.',
            ]);

            return ['sent' => 0, 'failed' => 0, 'invalid' => 0, 'expired' => 0, 'total' => $subscriptions->count()];
        }

        $auth = [
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $vapidPublic,
                'privateKey' => $vapidPrivate,
            ],
        ];

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ]);

        $sent = 0;
        $invalidCount = 0;
        $failedCount = 0;
        $expiredCount = 0;
        try {
            $webPush = new WebPush($auth);
            foreach ($subscriptions as $sub) {
                $keys = $sub->keys ?? [];
                $authKey = trim((string) ($keys['auth'] ?? ''));
                $p256dh = trim((string) ($keys['p256dh'] ?? ''));
                if (! $sub->endpoint || $authKey === '' || $p256dh === '') {
                    $invalidCount++;
                    Log::warning('PanelPushService: subscription com keys inválidas', ['subscription_id' => $sub->id]);
                    continue;
                }
                $subscription = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys' => [
                        'auth' => $this->normalizeBase64KeyForPush($authKey),
                        'p256dh' => $this->normalizeBase64KeyForPush($p256dh),
                    ],
                ]);
                try {
                    $report = $webPush->sendOneNotification($subscription, $payload);
                    if ($report->isSuccess()) {
                        $sent++;
                    } elseif ($report->isSubscriptionExpired()) {
                        $expiredCount++;
                        $sub->delete();
                        Log::info('PanelPushService: subscription expirada removida', ['subscription_id' => $sub->id]);
                    } else {
                        $failedCount++;
                        Log::warning('PanelPushService: envio falhou', [
                            'subscription_id' => $sub->id,
                            'reason' => $report->getReason(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    $failedCount++;
                    Log::warning('PanelPushService: falha ao enviar para subscription', [
                        'subscription_id' => $sub->id,
                        'tenant_id' => $sub->tenant_id,
                        'user_id' => $sub->user_id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
            if ($sent > 0) {
                Log::info('PanelPushService: push enviado', ['sent' => $sent, 'total' => $subscriptions->count()]);
            } else {
                Log::warning('PanelPushService: nenhum push entregue', [
                    'total_subscriptions' => $subscriptions->count(),
                    'invalid_subscriptions' => $invalidCount,
                    'expired_subscriptions' => $expiredCount,
                    'failed_subscriptions' => $failedCount,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('PanelPushService: erro ao enviar push', [
                'message' => $e->getMessage(),
                'total_subscriptions' => $subscriptions->count(),
            ]);
        }

        return [
            'sent' => $sent,
            'failed' => $failedCount,
            'invalid' => $invalidCount,
            'expired' => $expiredCount,
            'total' => $subscriptions->count(),
        ];
    }

    /**
     * Normaliza chave para o formato esperado pela minishlink/web-push (evita "Base64::decode() only expects characters in the correct base64 alphabet").
     * Converte base64 padrão (+/) para base64url (-_) se a lib esperar base64url; senão mantém padrão.
     */
    private function normalizeBase64KeyForPush(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return $key;
        }
        if (str_contains($key, '+') || str_contains($key, '/')) {
            return strtr($key, ['+' => '-', '/' => '_']);
        }
        return $key;
    }
}
