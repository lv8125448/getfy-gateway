<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\TeamAccessService;
use Inertia\Inertia;
use Inertia\Response;

class AssinaturasController extends Controller
{
    public function index(): Response
    {
        $tenantId = auth()->user()->tenant_id;
        $query = Subscription::with(['user', 'product', 'subscriptionPlan'])
            ->forTenant($tenantId)
            ->active();

        if (auth()->user()?->isTeam()) {
            $allowed = app(TeamAccessService::class)->allowedProductIdsFor(auth()->user());
            $query->whereIn('product_id', $allowed ?: ['__none__']);
        }

        $ativas = (clone $query)->count();
        $clientes = (clone $query)->distinct('user_id')->count('user_id');
        $mrrQuery = Subscription::forTenant($tenantId)->active()
            ->join('subscription_plans', 'subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
            ->where('subscription_plans.interval', '!=', 'lifetime');
        if (auth()->user()?->isTeam()) {
            $allowed = app(TeamAccessService::class)->allowedProductIdsFor(auth()->user());
            $mrrQuery->whereIn('subscriptions.product_id', $allowed ?: ['__none__']);
        }
        $mrr = round((float) $mrrQuery->sum('subscription_plans.price'), 2);

        $assinaturas = $query->orderByDesc('subscriptions.current_period_end')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($s) => [
                'id' => $s->id,
                'user' => $s->user ? ['id' => $s->user->id, 'name' => $s->user->name, 'email' => $s->user->email] : null,
                'product' => $s->product ? ['id' => $s->product->id, 'name' => $s->product->name] : null,
                'plan' => $s->subscriptionPlan ? ['id' => $s->subscriptionPlan->id, 'name' => $s->subscriptionPlan->name, 'interval' => $s->subscriptionPlan->interval, 'interval_label' => \App\Models\SubscriptionPlan::intervalLabels()[$s->subscriptionPlan->interval] ?? $s->subscriptionPlan->interval] : null,
                'current_period_end' => $s->current_period_end?->toDateString(),
                'status' => $s->status,
            ]);

        return Inertia::render('Assinaturas/Index', [
            'stats' => [
                'ativas' => $ativas,
                'clientes' => $clientes,
                'mrr' => $mrr,
            ],
            'assinaturas' => $assinaturas,
        ]);
    }
}
