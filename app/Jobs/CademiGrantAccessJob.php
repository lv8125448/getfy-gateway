<?php

namespace App\Jobs;

use App\Models\CademiIntegration;
use App\Models\Order;
use App\Models\CheckoutSession;
use App\Services\CademiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CademiGrantAccessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 30;

    public function __construct(
        public int $integrationId,
        public int $orderId
    ) {}

    public function handle(): void
    {
        $integration = CademiIntegration::find($this->integrationId);
        if (! $integration || ! $integration->is_active) {
            Log::info('CademiGrantAccessJob: integração inválida/inativa (skip)', [
                'integration_id' => $this->integrationId,
                'order_id' => $this->orderId,
            ]);
            return;
        }

        $order = Order::with(['user', 'product'])->find($this->orderId);
        if (! $order || $order->status !== 'completed') {
            Log::info('CademiGrantAccessJob: pedido inválido/não completed (skip)', [
                'integration_id' => $this->integrationId,
                'order_id' => $this->orderId,
                'status' => $order?->status,
            ]);
            return;
        }

        $mapping = $integration->resolveMappingForOrder($order);
        if ($mapping === null) {
            $this->storeOrderMeta($order, $this->buildMetaKey($integration, 0, 0), 'skipped', 'Cademí: sem mapping para este pedido.');
            return;
        }

        $tagId = isset($mapping['tag_id']) ? (int) $mapping['tag_id'] : 0;
        $produtoId = isset($mapping['produto_id']) ? (int) $mapping['produto_id'] : 0;
        $produtoIds = [];
        if (isset($mapping['produto_ids']) && is_array($mapping['produto_ids'])) {
            foreach ($mapping['produto_ids'] as $v) {
                $n = (int) $v;
                if ($n > 0) {
                    $produtoIds[] = $n;
                }
            }
            $produtoIds = array_values(array_unique($produtoIds));
        }
        if ($produtoIds === [] && $produtoId > 0) {
            $produtoIds = [$produtoId];
        }

        // Simple rate limiting: 2 req/sec per integration.
        $lockKey = 'cademi:rate:' . $integration->id;
        if (! Cache::add($lockKey, true, now()->addMilliseconds(600))) {
            $this->release(1);
            return;
        }

        $customer = [
            'email' => (string) ($order->email ?? ''),
            'name' => (string) ($order->user?->name ?? ''),
            'cpf' => (string) ($order->cpf ?? ''),
            'phone' => (string) ($order->phone ?? ''),
        ];

        $session = CheckoutSession::where('order_id', $order->id)->first();
        if ($session) {
            if (trim((string) ($session->email ?? '')) !== '') {
                $customer['email'] = (string) $session->email;
            }
            if (trim((string) ($session->name ?? '')) !== '') {
                $customer['name'] = (string) $session->name;
            }
        }

        $email = trim($customer['email']);
        if ($email === '') {
            $this->storeOrderMeta($order, $this->buildMetaKey($integration, $tagId, $produtoId), 'failed', 'Cademí: email do comprador ausente.');
            return;
        }

        // delivery_method decides which credential is required (API key vs postback token)
        $credential = $integration->isPostbackCustom()
            ? (string) ($integration->postback_token ?? '')
            : (string) ($integration->api_key ?? '');
        if (trim($credential) === '') {
            $this->storeOrderMeta($order, $this->buildMetaKey($integration, $tagId, $produtoId), 'failed', 'Cademí: credencial ausente para o método configurado.');
            return;
        }

        $service = new CademiService($integration->base_url, $credential);

        try {
            if ($integration->isPostbackCustom()) {
                if ($produtoIds === []) {
                    $this->storeOrderMeta($order, $this->buildMetaKey($integration, $tagId, 0), 'skipped', 'Cademí: produto_id não configurado (cademi_produto_id).');
                    return;
                }

                // tags (nome) é opcional. Só tentamos resolver nomes se houver API Key configurada.
                $tagNames = [];
                if ($tagId > 0 && trim((string) ($integration->api_key ?? '')) !== '') {
                    $tagsService = new CademiService($integration->base_url, (string) $integration->api_key);
                    $tagNames = $tagsService->resolveTagNames([$tagId]);
                }
                $tagsString = $tagNames ? implode(';', $tagNames) : null;

                foreach ($produtoIds as $pid) {
                    $metaKey = $this->buildMetaKey($integration, $tagId, $pid);
                    $meta = is_array($order->metadata ?? null) ? $order->metadata : [];
                    if (isset($meta[$metaKey]['status']) && in_array($meta[$metaKey]['status'], ['ok', 'skipped'], true)) {
                        continue;
                    }

                    $payload = array_filter([
                        'token' => $credential,
                        'codigo' => (string) $order->id,
                        'status' => 'aprovado',
                        'produto_id' => (string) $pid,
                        'produto_nome' => (string) ($order->product?->name ?? ''),
                        'valor' => (string) ($order->total ?? $order->amount ?? ''),
                        'cliente_email' => $email,
                        'cliente_nome' => $customer['name'] ?: $email,
                        'cliente_doc' => $customer['cpf'] ?: null,
                        'cliente_celular' => $customer['phone'] ?: null,
                        'tags' => $tagsString,
                    ], fn ($v) => $v !== null && $v !== '');

                    $res = $service->sendCustomPostback($payload);

                    $this->storeOrderMeta($order, $metaKey, 'ok', null, [
                        'delivery_method' => $integration->delivery_method,
                        'cademi_produto_id' => $pid,
                        'cademi_tag_id' => $tagId ?: null,
                        'tags' => $tagsString,
                        'postback_response' => $res,
                    ]);

                    Log::info('CademiGrantAccessJob: postback enviado', [
                        'order_id' => $order->id,
                        'integration_id' => $integration->id,
                        'produto_id' => $pid,
                        'tag_id' => $tagId ?: null,
                    ]);
                }
            } else {
                if ($tagId <= 0) {
                    $this->storeOrderMeta($order, $this->buildMetaKey($integration, 0, $produtoId), 'skipped', 'Cademí tag_id não configurada.');
                    return;
                }

                $user = $service->upsertUser([
                    'email' => $email,
                    'name' => $customer['name'] ?: $email,
                    'cpf' => $customer['cpf'] ?: null,
                    'phone' => $customer['phone'] ?: null,
                ]);

                $userId = isset($user['id']) ? (int) $user['id'] : 0;
                if ($userId <= 0) {
                    throw new \RuntimeException('Cademí: usuário sem id na resposta.');
                }

                $service->addTagToUser($userId, $tagId);

                $loginAuto = is_string($user['login_auto'] ?? null) ? $user['login_auto'] : null;
                $this->storeOrderMeta($order, $this->buildMetaKey($integration, $tagId, $produtoId), 'ok', null, [
                    'delivery_method' => $integration->delivery_method,
                    'cademi_user_id' => $userId,
                    'cademi_tag_id' => $tagId,
                    'login_auto' => $loginAuto,
                ]);

                Log::info('CademiGrantAccessJob: acesso concedido (tags_api)', [
                    'order_id' => $order->id,
                    'integration_id' => $integration->id,
                    'cademi_user_id' => $userId,
                    'tag_id' => $tagId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('CademiGrantAccessJob failed', [
                'order_id' => $order->id,
                'integration_id' => $integration->id,
                'message' => $e->getMessage(),
            ]);
            $this->storeOrderMeta($order, $this->buildMetaKey($integration, $tagId, $produtoId), 'failed', $e->getMessage() ?: 'Erro na integração Cademí.');
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function storeOrderMeta(Order $order, string $metaKey, string $status, ?string $message = null, array $extra = []): void
    {
        $meta = is_array($order->metadata ?? null) ? $order->metadata : [];
        $meta[$metaKey] = array_filter([
            'status' => $status,
            'message' => $message,
            'at' => now()->toIso8601String(),
        ] + $extra, fn ($v) => $v !== null && $v !== '');

        $order->update(['metadata' => $meta]);
    }

    private function buildMetaKey(CademiIntegration $integration, int $tagId, int $produtoId): string
    {
        return $integration->isPostbackCustom()
            ? ('cademi:' . $this->integrationId . ':postback:produto:' . ($produtoId > 0 ? $produtoId : 'null'))
            : ('cademi:' . $this->integrationId . ':tag:' . ($tagId > 0 ? $tagId : 'null'));
    }
}

