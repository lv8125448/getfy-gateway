<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Events\BoletoGenerated;
use App\Events\OrderCompleted;
use App\Events\OrderPending;
use App\Events\PixGenerated;
use App\Models\ApiApplication;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PaymentService;
use App\Support\FakeConsumerData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PaymentsController extends Controller
{
    /**
     * Resolve application from request (set by middleware).
     */
    private function application(Request $request): ApiApplication
    {
        $app = $request->attributes->get('api_application');
        if (! $app instanceof ApiApplication) {
            abort(500, 'API application not resolved');
        }
        return $app;
    }

    /**
     * Idempotency: return cached response if key was already used.
     *
     * @return JsonResponse|null  Cached response or null to continue
     */
    private function idempotencyReturn(int $appId, string $key, callable $buildResponse): JsonResponse
    {
        $cacheKey = 'idempotency:api:' . $appId . ':' . $key;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return response()->json($cached['body'], $cached['status']);
        }
        $response = $buildResponse();
        if ($response instanceof JsonResponse) {
            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'body' => json_decode($response->getContent(), true),
            ], now()->addHours(24));
        }
        return $response;
    }

    /**
     * Common customer validation and user resolution.
     */
    private function validateCustomerAndGetUser(Request $request, ApiApplication $app): array
    {
        $validated = $request->validate([
            'customer' => ['required', 'array'],
            'customer.email' => ['required', 'email'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.cpf' => ['nullable', 'string', 'max:14'],
            'customer.phone' => ['nullable', 'string', 'max:24'],
        ]);
        $customer = $validated['customer'];
        $email = $customer['email'];
        $name = trim((string) ($customer['name'] ?? ''));
        if ($name === '') {
            $name = $email;
        }
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => bcrypt(Str::random(32)),
                'role' => User::ROLE_ALUNO,
                'tenant_id' => $app->tenant_id,
            ]
        );
        return [
            'user' => $user,
            'consumer' => [
                'name' => $name,
                'document' => preg_replace('/\D/', '', (string) ($customer['cpf'] ?? '')),
                'email' => $email,
            ],
            'cpf' => $customer['cpf'] ?? null,
            'phone' => $customer['phone'] ?? null,
        ];
    }

    /**
     * Create order for API (with or without product).
     *
     * @return array{order: Order, product: Product|null, amount: float, gateway_config: array}
     */
    private function createOrderForApi(Request $request, ApiApplication $app, float $amount, string $currency, ?string $productId, ?int $productOfferId, ?int $subscriptionPlanId, string $paymentMethod, array $metadata, array $userConsumer): array
    {
        $tenantId = $app->tenant_id;
        $product = null;
        $productOfferId = $productOfferId ?: null;
        $subscriptionPlanId = $subscriptionPlanId ?: null;
        $orderAmount = $amount;
        $periodStart = null;
        $periodEnd = null;

        if ($productId !== null && $productId !== '') {
            $product = Product::where('id', $productId)->where('tenant_id', $tenantId)->first();
            if (! $product) {
                abort(422, 'Produto não encontrado.');
            }
            $offer = $productOfferId ? ProductOffer::where('id', $productOfferId)->where('product_id', $product->id)->first() : null;
            $plan = $subscriptionPlanId ? SubscriptionPlan::where('id', $subscriptionPlanId)->where('product_id', $product->id)->first() : null;
            if ($offer) {
                $orderAmount = (float) $offer->price;
                $currency = $offer->getCurrencyOrDefault();
            } elseif ($plan) {
                $orderAmount = (float) $plan->price;
                $currency = $plan->getCurrencyOrDefault();
                [$periodStart, $periodEnd] = $plan->getCurrentPeriod();
            } else {
                $orderAmount = (float) $product->price;
                $currency = $product->currency ?? 'BRL';
            }
        }

        $rates = config('products.rates', ['brl_eur' => 0.16, 'brl_usd' => 0.18]);
        if ($currency !== 'BRL') {
            $orderAmount = $currency === 'EUR' ? $orderAmount / ($rates['brl_eur'] ?? 0.16) : $orderAmount / ($rates['brl_usd'] ?? 0.18);
        }

        $consumer = $userConsumer['consumer'];
        $fake = FakeConsumerData::getForGateway(mt_rand(1, 999999));
        if (strlen($consumer['document'] ?? '') < 11) {
            $consumer['document'] = $fake['document'];
        }
        if (trim($consumer['name'] ?? '') === '') {
            $consumer['name'] = $fake['name'];
        }

        $metadata['checkout_payment_method'] = $paymentMethod;

        $order = Order::create([
            'tenant_id' => $tenantId,
            'user_id' => $userConsumer['user']->id,
            'product_id' => $product?->id,
            'product_offer_id' => $productOfferId,
            'subscription_plan_id' => $subscriptionPlanId,
            'api_application_id' => $app->id,
            'api_checkout_session_id' => null,
            'status' => 'pending',
            'amount' => $orderAmount,
            'email' => $consumer['email'],
            'cpf' => $userConsumer['cpf'] ?? null,
            'phone' => $userConsumer['phone'] ?? null,
            'customer_ip' => $request->ip(),
            'coupon_code' => null,
            'gateway' => null,
            'gateway_id' => null,
            'payment_method' => $paymentMethod,
            'metadata' => $metadata,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'is_renewal' => false,
        ]);

        if ($product !== null) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_offer_id' => $productOfferId,
                'subscription_plan_id' => $subscriptionPlanId,
                'amount' => $orderAmount,
                'position' => 0,
            ]);
        }

        $gatewayConfig = $app->payment_gateways ?? ApiApplication::defaultPaymentGateways();

        return [
            'order' => $order,
            'product' => $product,
            'amount' => $orderAmount,
            'consumer' => $consumer,
            'gateway_config' => $gatewayConfig,
        ];
    }

    public function createPix(Request $request): JsonResponse
    {
        $app = $this->application($request);
        $validated = $request->validate([
            'customer' => ['required', 'array'],
            'customer.email' => ['required', 'email'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.cpf' => ['nullable', 'string', 'max:14'],
            'customer.phone' => ['nullable', 'string', 'max:24'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'in:BRL,USD,EUR'],
            'product_id' => ['nullable', 'string', 'exists:products,id'],
            'product_offer_id' => ['nullable', 'integer', 'exists:product_offers,id'],
            'subscription_plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $idemKey = $request->input('idempotency_key') ?: $request->header('Idempotency-Key');
        if ($idemKey !== null && $idemKey !== '') {
            return $this->idempotencyReturn($app->id, $idemKey, function () use ($request, $app, $validated) {
                return $this->doCreatePix($request, $app, $validated);
            });
        }

        return $this->doCreatePix($request, $app, $validated);
    }

    private function doCreatePix(Request $request, ApiApplication $app, array $validated): JsonResponse
    {
        $userConsumer = $this->validateCustomerAndGetUser($request, $app);
        $amount = (float) $validated['amount'];
        $currency = strtoupper((string) ($validated['currency'] ?? 'BRL'));
        $metadata = $validated['metadata'] ?? [];
        $metadata['source'] = 'api';

        $ctx = $this->createOrderForApi(
            $request,
            $app,
            $amount,
            $currency,
            $validated['product_id'] ?? null,
            $validated['product_offer_id'] ?? null,
            $validated['subscription_plan_id'] ?? null,
            'pix',
            $metadata,
            $userConsumer
        );

        $order = $ctx['order'];
        $paymentService = app(PaymentService::class);

        try {
            event(new OrderPending($order));
            $result = $paymentService->createPixPayment($order, $ctx['product'], $ctx['consumer'], $ctx['gateway_config']);
            event(new PixGenerated($order, [
                'qrcode' => $result['qrcode'] ?? null,
                'copy_paste' => $result['copy_paste'] ?? null,
                'transaction_id' => $result['transaction_id'] ?? null,
            ]));

            return response()->json([
                'order_id' => $order->id,
                'transaction_id' => $result['transaction_id'] ?? null,
                'qrcode' => $result['qrcode'] ?? null,
                'copy_paste' => $result['copy_paste'] ?? null,
                'status' => 'pending',
            ], 201);
        } catch (\Throwable $e) {
            $order->delete();
            return response()->json([
                'message' => $e->getMessage() ?: 'Não foi possível gerar o PIX.',
            ], 422);
        }
    }

    public function createCard(Request $request): JsonResponse
    {
        $app = $this->application($request);
        $validated = $request->validate([
            'customer' => ['required', 'array'],
            'customer.email' => ['required', 'email'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.cpf' => ['nullable', 'string', 'max:14'],
            'customer.phone' => ['nullable', 'string', 'max:24'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'in:BRL,USD,EUR'],
            'product_id' => ['nullable', 'string', 'exists:products,id'],
            'product_offer_id' => ['nullable', 'integer', 'exists:product_offers,id'],
            'subscription_plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
            'card' => ['required', 'array'],
            'card.payment_token' => ['required', 'string', 'max:10000'],
        ]);

        $idemKey = $request->input('idempotency_key') ?: $request->header('Idempotency-Key');
        if ($idemKey !== null && $idemKey !== '') {
            return $this->idempotencyReturn($app->id, $idemKey, function () use ($request, $app, $validated) {
                return $this->doCreateCard($request, $app, $validated);
            });
        }

        return $this->doCreateCard($request, $app, $validated);
    }

    private function doCreateCard(Request $request, ApiApplication $app, array $validated): JsonResponse
    {
        $userConsumer = $this->validateCustomerAndGetUser($request, $app);
        $amount = (float) $validated['amount'];
        $currency = strtoupper((string) ($validated['currency'] ?? 'BRL'));
        $metadata = $validated['metadata'] ?? [];
        $metadata['source'] = 'api';

        $ctx = $this->createOrderForApi(
            $request,
            $app,
            $amount,
            $currency,
            $validated['product_id'] ?? null,
            $validated['product_offer_id'] ?? null,
            $validated['subscription_plan_id'] ?? null,
            'card',
            $metadata,
            $userConsumer
        );

        $order = $ctx['order'];
        $paymentService = app(PaymentService::class);
        $card = ['payment_token' => $validated['card']['payment_token'], 'card_mask' => $validated['card']['card_mask'] ?? null];

        try {
            event(new OrderPending($order));
            $result = $paymentService->createCardPayment($order, $ctx['product'], $ctx['consumer'], $card, $ctx['gateway_config']);
            $status = $result['status'] ?? 'pending';
            if ($status === 'paid' || $status === 'approved' || $status === 'completed') {
                $order->update(['status' => 'completed', 'payment_method' => 'card']);
                $order->grantPurchasedProductAccessToBuyer();
                event(new OrderCompleted($order));
            }

            $response = [
                'order_id' => $order->id,
                'transaction_id' => $result['transaction_id'] ?? null,
                'status' => $status,
            ];
            if (isset($result['client_secret'])) {
                $response['client_secret'] = $result['client_secret'];
            }
            return response()->json($response, 201);
        } catch (\Throwable $e) {
            $order->delete();
            return response()->json([
                'message' => $e->getMessage() ?: 'Falha no pagamento com cartão.',
            ], 422);
        }
    }

    public function createBoleto(Request $request): JsonResponse
    {
        $app = $this->application($request);
        $validated = $request->validate([
            'customer' => ['required', 'array'],
            'customer.email' => ['required', 'email'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.cpf' => ['nullable', 'string', 'max:14'],
            'customer.phone' => ['nullable', 'string', 'max:24'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'in:BRL,USD,EUR'],
            'product_id' => ['nullable', 'string', 'exists:products,id'],
            'product_offer_id' => ['nullable', 'integer', 'exists:product_offers,id'],
            'subscription_plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $idemKey = $request->input('idempotency_key') ?: $request->header('Idempotency-Key');
        if ($idemKey !== null && $idemKey !== '') {
            return $this->idempotencyReturn($app->id, $idemKey, function () use ($request, $app, $validated) {
                return $this->doCreateBoleto($request, $app, $validated);
            });
        }

        return $this->doCreateBoleto($request, $app, $validated);
    }

    private function doCreateBoleto(Request $request, ApiApplication $app, array $validated): JsonResponse
    {
        $userConsumer = $this->validateCustomerAndGetUser($request, $app);
        $amount = (float) $validated['amount'];
        $currency = strtoupper((string) ($validated['currency'] ?? 'BRL'));
        $metadata = $validated['metadata'] ?? [];
        $metadata['source'] = 'api';

        $ctx = $this->createOrderForApi(
            $request,
            $app,
            $amount,
            $currency,
            $validated['product_id'] ?? null,
            $validated['product_offer_id'] ?? null,
            $validated['subscription_plan_id'] ?? null,
            'boleto',
            $metadata,
            $userConsumer
        );

        $order = $ctx['order'];
        $paymentService = app(PaymentService::class);

        try {
            event(new OrderPending($order));
            $result = $paymentService->createBoletoPayment($order, $ctx['product'], $ctx['consumer'], $ctx['gateway_config']);
            $boletoData = [
                'amount' => $result['amount'] ?? $order->amount,
                'expire_at' => $result['expire_at'] ?? null,
                'barcode' => $result['barcode'] ?? null,
                'pdf_url' => $result['pdf_url'] ?? null,
            ];
            event(new BoletoGenerated($order, $boletoData));

            return response()->json([
                'order_id' => $order->id,
                'transaction_id' => $result['transaction_id'] ?? null,
                'barcode' => $result['barcode'] ?? '',
                'pdf_url' => $result['pdf_url'] ?? '',
                'expire_at' => $result['expire_at'] ?? '',
                'amount' => $result['amount'] ?? $order->amount,
                'status' => 'pending',
            ], 201);
        } catch (\Throwable $e) {
            $order->delete();
            return response()->json([
                'message' => $e->getMessage() ?: 'Não foi possível gerar o boleto.',
            ], 422);
        }
    }
}
