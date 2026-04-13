<?php

namespace App\Http\Controllers;

use App\Events\BoletoGenerated;
use App\Events\OrderCompleted;
use App\Events\OrderPending;
use App\Events\PixGenerated;
use App\Events\SubscriptionRenewed;
use App\Models\GatewayCredential;
use App\Models\Order;
use App\Models\Subscription;
use App\Services\EfiPixRecorrenteService;
use App\Services\PaymentService;
use App\Support\FakeConsumerData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class RenewalController extends Controller
{
    public function show(string $token): Response|RedirectResponse
    {
        $subscription = Subscription::with(['user', 'product', 'subscriptionPlan'])
            ->where('renewal_token', $token)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->first();

        if (! $subscription || $subscription->subscriptionPlan->isLifetime()) {
            return redirect()->route('login')->with('error', 'Link de renovação inválido ou expirado.');
        }

        $product = $subscription->product;
        $plan = $subscription->subscriptionPlan;
        $amount = (float) $plan->price;
        $currency = $plan->getCurrencyOrDefault();
        $rates = config('products.rates', ['brl_eur' => 0.16, 'brl_usd' => 0.18]);
        if ($currency !== 'BRL') {
            $amount = $currency === 'EUR' ? $amount / ($rates['brl_eur'] ?? 0.16) : $amount / ($rates['brl_usd'] ?? 0.18);
        }

        $tenantId = $subscription->tenant_id;
        $availablePaymentMethods = app(PaymentService::class)->availablePaymentMethodsForCheckout($product, $plan, $subscription);
        $savedPaymentMethods = $subscription->user->savedPaymentMethods()->forTenant($tenantId)->get()->map(fn ($m) => [
            'id' => $m->id,
            'type' => $m->type,
            'last_four' => $m->last_four,
            'brand' => $m->brand,
        ])->all();

        $productArray = [
            'id' => $product->id,
            'name' => $product->name,
            'checkout_slug' => $plan->checkout_slug,
        ];
        $planArray = [
            'id' => $plan->id,
            'name' => $plan->name,
            'price' => (float) $plan->price,
            'currency' => $currency,
            'interval' => $plan->interval,
        ];

        return Inertia::render('Renewal/Show', [
            'token' => $token,
            'subscription' => [
                'id' => $subscription->id,
                'current_period_end' => $subscription->current_period_end?->toDateString(),
            ],
            'product' => $productArray,
            'plan' => $planArray,
            'amount' => round($amount, 2),
            'amount_brl' => round($amount, 2),
            'available_payment_methods' => $availablePaymentMethods,
            'saved_payment_methods' => $savedPaymentMethods,
        ]);
    }

    public function process(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'max:64'],
            'payment_method' => ['nullable', 'string', 'in:pix,card,boleto,pix_auto,manual'],
        ]);

        $subscription = Subscription::with(['user', 'product', 'subscriptionPlan'])
            ->where('renewal_token', $request->input('token'))
            ->where('status', Subscription::STATUS_ACTIVE)
            ->first();

        if (! $subscription || $subscription->subscriptionPlan->isLifetime()) {
            return redirect()->route('login')->with('error', 'Link de renovação inválido ou expirado.');
        }

        $product = $subscription->product;
        $plan = $subscription->subscriptionPlan;
        $user = $subscription->user;
        $tenantId = $subscription->tenant_id;
        $amount = (float) $plan->price;
        $currency = $plan->getCurrencyOrDefault();
        $rates = config('products.rates', ['brl_eur' => 0.16, 'brl_usd' => 0.18]);
        if ($currency !== 'BRL') {
            $amount = $currency === 'EUR' ? $amount / ($rates['brl_eur'] ?? 0.16) : $amount / ($rates['brl_usd'] ?? 0.18);
        }

        [$periodStart, $periodEnd] = $plan->getCurrentPeriod();
        $paymentMethod = $request->input('payment_method', 'manual');

        if ($paymentMethod !== 'manual') {
            $allowedIds = array_column(
                app(PaymentService::class)->availablePaymentMethodsForCheckout($product, $plan, $subscription),
                'id'
            );
            if (! in_array($paymentMethod, $allowedIds, true)) {
                return back()->with('error', 'Método de pagamento não disponível para este produto.');
            }
        }

        $orderPayload = [
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_offer_id' => null,
            'subscription_plan_id' => $plan->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'is_renewal' => true,
            'amount' => $amount,
            'email' => $user->email,
            'cpf' => null,
            'phone' => null,
            'customer_ip' => $request->ip(),
            'coupon_code' => null,
        ];

        if ($paymentMethod === 'pix_auto' && $subscription->gateway_subscription_id) {
            $credential = GatewayCredential::resolveForPayment($tenantId, 'efi');
            if ($credential) {
                $credentials = $credential->getDecryptedCredentials();
                if (! empty($credentials['certificate_path'])) {
                    $order = Order::create(array_merge($orderPayload, [
                        'status' => 'pending',
                        'gateway' => 'efi',
                        'gateway_id' => null,
                        'payment_method' => 'pix_auto',
                        'metadata' => ['checkout_payment_method' => 'pix_auto'],
                    ]));
                    event(new OrderPending($order));
                    try {
                        $idRec = $subscription->gateway_subscription_id;
                        $dataDeVencimento = $periodEnd ? $periodEnd->format('Y-m-d') : now()->addMonth()->format('Y-m-d');
                        $base = 'pixautorenov' . $order->id;
                        $txid = $base . \Illuminate\Support\Str::random(max(26 - strlen($base), 10));
                        $txid = substr($txid, 0, 35);
                        $devedor = [
                            'name' => $user->name ?? $user->email,
                            'email' => $user->email,
                        ];
                        $service = new EfiPixRecorrenteService($credentials);
                        $service->createCobrancaRecorrente(
                            $idRec,
                            $amount,
                            $dataDeVencimento,
                            $txid,
                            $devedor,
                            'Renovação assinatura #' . $subscription->id
                        );
                        $order->update(['gateway_id' => $txid]);
                        return redirect()->route('renewal.show', $request->input('token'))
                            ->with('info', 'O débito PIX automático foi agendado. Você receberá a confirmação quando o pagamento for processado.');
                    } catch (\Throwable $e) {
                        $order->delete();
                        return back()->with('error', $e->getMessage() ?: 'Não foi possível agendar o PIX automático. Tente outro método.');
                    }
                }
            }
        }

        if ($paymentMethod === 'pix') {
            $order = Order::create(array_merge($orderPayload, [
                'status' => 'pending',
                'gateway' => null,
                'gateway_id' => null,
                'payment_method' => 'pix',
                'metadata' => ['checkout_payment_method' => 'pix'],
            ]));
            event(new OrderPending($order));
            try {
                $paymentService = app(PaymentService::class);
                $fake = FakeConsumerData::getForGateway($order->id);
                $consumer = [
                    'name' => $user->name ?? $user->email,
                    'document' => $fake['document'],
                    'email' => $user->email,
                ];
                $pixResult = $paymentService->createPixPayment($order, $product, $consumer);
                event(new PixGenerated($order, [
                    'qrcode' => $pixResult['qrcode'] ?? null,
                    'copy_paste' => $pixResult['copy_paste'] ?? null,
                    'transaction_id' => $pixResult['transaction_id'] ?? null,
                ]));
                $pixToken = \Illuminate\Support\Str::random(32);
                session()->put('pix_display.' . $pixToken, [
                    'order_id' => $order->id,
                    'qrcode' => $pixResult['qrcode'] ?? null,
                    'copy_paste' => $pixResult['copy_paste'] ?? null,
                    'amount' => $amount,
                    'product_name' => $product->name,
                    'checkout_slug' => $plan->checkout_slug,
                    'redirect_after_purchase' => null,
                    'customer_name' => $user->name,
                    'customer_email' => $user->email,
                    'customer_phone' => null,
                    'created_at' => time(),
                ]);
                return redirect()->route('checkout.pix', ['token' => $pixToken]);
            } catch (\Throwable $e) {
                $order->delete();
                return back()->with('error', $e->getMessage() ?: 'Não foi possível gerar o PIX. Tente novamente.');
            }
        }

        if ($paymentMethod === 'card' || $paymentMethod === 'boleto') {
            $order = Order::create(array_merge($orderPayload, [
                'status' => 'pending',
                'gateway' => $paymentMethod,
                'gateway_id' => null,
                'payment_method' => $paymentMethod,
            ]));
            event(new OrderPending($order));
            if ($paymentMethod === 'boleto') {
                event(new BoletoGenerated($order));
            }
            $methodLabel = $paymentMethod === 'card' ? 'Cartão' : 'Boleto';
            return redirect()->route('renewal.show', $request->input('token'))
                ->with('info', "Pagamento por {$methodLabel} em breve. Você receberá as instruções por e-mail.");
        }

        $order = Order::create(array_merge($orderPayload, [
            'status' => 'completed',
            'gateway' => 'manual',
            'payment_method' => 'pix',
        ]));
        $subscription->update([
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
        ]);
        event(new SubscriptionRenewed($subscription->fresh()));
        event(new OrderCompleted($order));

        return redirect()->route('renewal.show', $request->input('token'))
            ->with('success', 'Renovação concluída. Seu acesso foi estendido.');
    }

}
