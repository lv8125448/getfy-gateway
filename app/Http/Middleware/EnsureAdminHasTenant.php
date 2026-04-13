<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EnsureAdminHasTenant
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && ($user->role === User::ROLE_INFOPRODUTOR || $user->role === User::ROLE_ADMIN) && $user->tenant_id === null) {
            $user->update(['tenant_id' => $user->id]);
        }

        if ($user && ($user->role === User::ROLE_INFOPRODUTOR || $user->role === User::ROLE_ADMIN) && $user->tenant_id) {
            // Migração automática 1x: dados antigos com tenant_id = null no legado.
            $cacheKey = 'tenant_owner_backfill_done:' . $user->id;
            if (! Cache::get($cacheKey)) {
                DB::transaction(function () use ($user) {
                    $tenantId = $user->tenant_id ?? $user->id;

                    DB::table('products')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
                    DB::table('webhooks')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
                    // settings e gateway_credentials com tenant_id null são configuração global da plataforma — não reatribuir.
                    DB::table('coupons')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
                    DB::table('api_applications')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);

                    // Vendas antigas do admin: pedidos e sessões criados quando tenant_id ainda era null.
                    // Atualizamos apenas pedidos cujos produtos já pertencem ao tenant do admin.
                    DB::table('orders')
                        ->whereNull('orders.tenant_id')
                        ->whereIn('orders.product_id', function ($q) use ($tenantId) {
                            $q->select('id')->from('products')->where('tenant_id', $tenantId);
                        })
                        ->update(['tenant_id' => $tenantId]);

                    // PostgreSQL: checkout_sessions.product_id pode ser bigint legado enquanto products.id é UUID (char);
                    // o subselect direto gera "operator does not exist: bigint = character". Comparar como texto.
                    if (DB::getDriverName() === 'pgsql') {
                        DB::update(
                            'update checkout_sessions set tenant_id = ? where tenant_id is null and product_id::text in (select id::text from products where tenant_id = ?)',
                            [$tenantId, $tenantId]
                        );
                    } else {
                        DB::table('checkout_sessions')
                            ->whereNull('tenant_id')
                            ->whereIn('product_id', function ($q) use ($tenantId) {
                                $q->select('id')->from('products')->where('tenant_id', $tenantId);
                            })
                            ->update(['tenant_id' => $tenantId]);
                    }

                    DB::table('subscriptions')
                        ->whereNull('tenant_id')
                        ->whereIn('product_id', function ($q) use ($tenantId) {
                            $q->select('id')->from('products')->where('tenant_id', $tenantId);
                        })
                        ->update(['tenant_id' => $tenantId]);
                });

                Cache::put($cacheKey, true, now()->addDays(365));
            }
        }

        return $next($request);
    }
}

