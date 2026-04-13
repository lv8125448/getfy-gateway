<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\EffectiveMerchantFees;
use App\Services\OrderManualApprovalService;
use App\Services\PlatformOrderAdminService;
use App\Services\PlatformAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TransactionsController extends Controller
{
    private const STATUS_OPTIONS = ['all', 'pending', 'completed', 'disputed', 'cancelled', 'refunded'];

    private function paymentMethodForFees(Order $order): string
    {
        $method = $order->payment_method;
        if ($method === null || $method === '') {
            $meta = $order->metadata ?? [];
            $method = is_array($meta) ? ($meta['checkout_payment_method'] ?? null) : null;
        }

        return (string) ($method ?: 'pix');
    }

    /**
     * @return array{gross: float, fee: float, net: float}
     */
    private function orderFeeBreakdown(Order $order): array
    {
        $gross = (float) $order->lineItemsTotalAmount();
        $tenantId = (int) $order->tenant_id;
        if ($tenantId < 1) {
            return ['gross' => $gross, 'fee' => 0.0, 'net' => $gross];
        }
        $calc = EffectiveMerchantFees::calculateSaleFee($tenantId, $this->paymentMethodForFees($order), $gross);

        return [
            'gross' => $gross,
            'fee' => $calc['fee'],
            'net' => $calc['net'],
        ];
    }

    private function productDisplayName(Order $order): string
    {
        return $this->orderProductLabel($order);
    }

    private function paymentTypeLabel(Order $order): string
    {
        if ($order->subscription_plan_id || $order->is_renewal) {
            return 'Pagamento recorrente';
        }

        return 'Pagamento único';
    }

    public function index(Request $request): Response
    {
        $status = $request->query('status', 'all');
        if (! in_array($status, self::STATUS_OPTIONS, true)) {
            $status = 'all';
        }
        $q = trim((string) $request->query('q', ''));

        $ordersPaginator = new LengthAwarePaginator([], 0, 40, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        if (Schema::hasTable('orders')) {
            $query = Order::query()
                ->with([
                    'user:id,name,email',
                    'tenantOwner:id,name,email',
                    'product:id,name,slug,checkout_slug',
                    'productOffer:id,name,checkout_slug',
                    'subscriptionPlan:id,name,checkout_slug',
                    'checkoutSession:id,order_id,utm_source,utm_medium,utm_campaign',
                    'orderItems:id,order_id,product_id,product_offer_id,subscription_plan_id,amount,position',
                    'orderItems.product:id,name',
                    'orderItems.productOffer:id,name',
                    'orderItems.subscriptionPlan:id,name',
                ])
                ->orderByDesc('created_at');

            if ($status !== 'all') {
                $query->where('orders.status', $status);
            }

            if ($q !== '') {
                $query->where(function ($w) use ($q) {
                    $w->where('orders.email', 'like', '%'.$q.'%')
                        ->orWhereHas('user', function ($u) use ($q) {
                            $u->where('name', 'like', '%'.$q.'%')
                                ->orWhere('email', 'like', '%'.$q.'%');
                        });
                    if (ctype_digit($q)) {
                        $w->orWhere('orders.id', $q);
                    }
                });
            }

            $ordersPaginator = $query->paginate(40)->withQueryString()->through(function (Order $o) {
                $arr = $o->toArray();
                $breakdown = $this->orderFeeBreakdown($o);
                $arr['gateway_label'] = $o->paymentMethodDisplayLabel();
                $arr['product_display_name'] = $this->productDisplayName($o);
                $arr['checkout_url'] = url('/c/'.$o->getCheckoutSlug());
                $arr['payment_type_label'] = $this->paymentTypeLabel($o);
                $arr['amount_total'] = $breakdown['gross'];
                $arr['amount_gross'] = $breakdown['gross'];
                $arr['amount_fee'] = $breakdown['fee'];
                $arr['amount_net'] = $breakdown['net'];
                $arr['product_label'] = $this->orderProductLabel($o);
                $arr['customer_name'] = $o->user?->name ?? '—';
                $arr['customer_email'] = $o->user?->email ?? $o->email ?? '—';
                $arr['infoprodutor_name'] = $o->tenantOwner?->name ?? '—';
                $arr['infoprodutor_email'] = $o->tenantOwner?->email;
                $arr['payment_method_label'] = $o->paymentMethodDisplayLabel();

                return $arr;
            });
        }

        return Inertia::render('Platform/Transactions/Index', [
            'orders' => $ordersPaginator,
            'filters' => [
                'status' => $status,
                'q' => $q,
            ],
        ]);
    }

    private function orderActionRedirectParams(Request $request): array
    {
        return array_filter([
            'status' => $request->query('status'),
            'q' => $request->query('q'),
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function approveManualOrder(Request $request, Order $order): RedirectResponse
    {
        $redirectParams = $this->orderActionRedirectParams($request);

        try {
            OrderManualApprovalService::approve($order);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('plataforma.transacoes.index', $redirectParams)
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->route('plataforma.transacoes.index', $redirectParams)
                ->with('error', 'Não foi possível concluir a aprovação: '.$e->getMessage());
        }

        PlatformAuditService::log('platform.order.approved_manually', [
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
        ], $request);

        return redirect()->route('plataforma.transacoes.index', $redirectParams)
            ->with('success', 'Pedido #'.$order->id.' aprovado. O cliente recebeu acesso conforme o produto.');
    }

    public function cancelOrder(Request $request, Order $order): RedirectResponse
    {
        $redirectParams = $this->orderActionRedirectParams($request);

        try {
            PlatformOrderAdminService::cancelPending($order);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('plataforma.transacoes.index', $redirectParams)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->route('plataforma.transacoes.index', $redirectParams)
                ->with('error', 'Não foi possível cancelar: '.$e->getMessage());
        }

        PlatformAuditService::log('platform.order.cancelled', [
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
        ], $request);

        return redirect()->route('plataforma.transacoes.index', $redirectParams)
            ->with('success', 'Pedido #'.$order->id.' cancelado.');
    }

    public function refundOrder(Request $request, Order $order): RedirectResponse
    {
        $redirectParams = $this->orderActionRedirectParams($request);

        try {
            PlatformOrderAdminService::refundPaidOrDisputed($order);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('plataforma.transacoes.index', $redirectParams)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->route('plataforma.transacoes.index', $redirectParams)
                ->with('error', 'Não foi possível reembolsar: '.$e->getMessage());
        }

        PlatformAuditService::log('platform.order.refunded', [
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
        ], $request);

        return redirect()->route('plataforma.transacoes.index', $redirectParams)
            ->with('success', 'Pedido #'.$order->id.' marcado como reembolsado.');
    }

    public function markDisputedOrder(Request $request, Order $order): RedirectResponse
    {
        $redirectParams = $this->orderActionRedirectParams($request);

        try {
            PlatformOrderAdminService::markDisputed($order);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('plataforma.transacoes.index', $redirectParams)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->route('plataforma.transacoes.index', $redirectParams)
                ->with('error', 'Não foi possível atualizar: '.$e->getMessage());
        }

        PlatformAuditService::log('platform.order.disputed', [
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
        ], $request);

        return redirect()->route('plataforma.transacoes.index', $redirectParams)
            ->with('success', 'Pedido #'.$order->id.' marcado como MED.');
    }

    private function orderProductLabel(Order $order): string
    {
        $product = $order->product;
        if (! $product) {
            return '—';
        }
        $name = $product->name;
        if ($order->productOffer) {
            $name .= ' - '.$order->productOffer->name;
        } elseif ($order->subscriptionPlan) {
            $name .= ' - '.$order->subscriptionPlan->name;
        }

        return $name;
    }
}
