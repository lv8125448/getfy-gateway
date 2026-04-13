<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductAffiliateEnrollment;
use App\Services\StorageService;
use App\Services\TeamAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $allowedProductIds = null;
        if ($user->isTeam()) {
            $allowedProductIds = app(TeamAccessService::class)->allowedProductIdsFor($user);
            if ($allowedProductIds === []) {
                return Inertia::render('Afiliados/MeusAfiliados', [
                    'enrollments' => ProductAffiliateEnrollment::query()->whereRaw('0 = 1')->paginate(20),
                    'filters' => [
                        'status' => $request->query('status', 'all'),
                        'product_id' => $request->query('product_id'),
                        'q' => $request->query('q'),
                    ],
                    'products_filter' => [],
                ]);
            }
        }

        $statusFilter = $request->query('status', 'all');
        $productIdFilter = $request->query('product_id');
        $q = trim((string) $request->query('q', ''));

        $query = ProductAffiliateEnrollment::query()
            ->with([
                'affiliate:id,name,email',
                'product:id,name,tenant_id,image',
            ])
            ->whereHas('product', function ($q) use ($tenantId, $allowedProductIds) {
                $q->where('tenant_id', $tenantId);
                if (is_array($allowedProductIds)) {
                    $q->whereIn('id', $allowedProductIds);
                }
            })
            ->orderByDesc('updated_at');

        if ($statusFilter === 'blocked') {
            $query->whereIn('status', [
                ProductAffiliateEnrollment::STATUS_REJECTED,
                ProductAffiliateEnrollment::STATUS_REVOKED,
            ]);
        } elseif ($statusFilter !== 'all' && in_array($statusFilter, [
            ProductAffiliateEnrollment::STATUS_PENDING,
            ProductAffiliateEnrollment::STATUS_APPROVED,
            ProductAffiliateEnrollment::STATUS_REJECTED,
            ProductAffiliateEnrollment::STATUS_REVOKED,
        ], true)) {
            $query->where('status', $statusFilter);
        }

        if ($productIdFilter !== null && $productIdFilter !== '') {
            $query->where('product_id', $productIdFilter);
        }

        if ($q !== '') {
            $query->whereHas('affiliate', function ($sub) use ($q) {
                $sub->where('name', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%');
            });
        }

        $paginator = $query->paginate(20)->withQueryString();

        $storage = app(StorageService::class);
        $enrollments = $paginator->through(function (ProductAffiliateEnrollment $e) use ($storage) {
            $p = $e->product;
            $a = $e->affiliate;

            return [
                'id' => $e->id,
                'status' => $e->status,
                'public_ref' => $e->public_ref,
                'updated_at' => $e->updated_at?->toIso8601String(),
                'created_at' => $e->created_at?->toIso8601String(),
                'product' => $p ? [
                    'id' => $p->id,
                    'name' => $p->name,
                    'image_url' => $p->image ? $storage->url($p->image) : null,
                ] : null,
                'affiliate' => $a ? [
                    'id' => $a->id,
                    'name' => $a->name,
                    'email' => $a->email,
                ] : null,
            ];
        });

        $productsForFilter = Product::query()
            ->forTenant($tenantId)
            ->when(is_array($allowedProductIds), fn ($q) => $q->whereIn('id', $allowedProductIds))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name])
            ->values()
            ->all();

        return Inertia::render('Afiliados/MeusAfiliados', [
            'enrollments' => $enrollments,
            'filters' => [
                'status' => $statusFilter,
                'product_id' => $productIdFilter ?: null,
                'q' => $q !== '' ? $q : null,
            ],
            'products_filter' => $productsForFilter,
        ]);
    }

    public function approve(Request $request, ProductAffiliateEnrollment $enrollment): RedirectResponse
    {
        $product = $this->authorizeEnrollment($enrollment);

        if ($enrollment->status !== ProductAffiliateEnrollment::STATUS_PENDING) {
            return back()->with('error', 'Esta solicitação não está pendente.');
        }

        $product->refresh();
        if ($product->affiliate_enabled && ! $product->affiliateCommissionTotalsValid()) {
            return back()->with('error', 'Não é possível aprovar: a soma de comissões (co-produção + afiliado) excede 100%.');
        }

        $enrollment->update(['status' => ProductAffiliateEnrollment::STATUS_APPROVED]);
        $enrollment->ensurePublicRef();

        return back()->with('success', 'Afiliação aprovada.');
    }

    public function reject(Request $request, ProductAffiliateEnrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($enrollment);

        if ($enrollment->status !== ProductAffiliateEnrollment::STATUS_PENDING) {
            return back()->with('error', 'Esta solicitação não está pendente.');
        }

        $enrollment->update(['status' => ProductAffiliateEnrollment::STATUS_REJECTED]);

        return back()->with('success', 'Solicitação recusada.');
    }

    public function revoke(Request $request, ProductAffiliateEnrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($enrollment);

        if ($enrollment->status !== ProductAffiliateEnrollment::STATUS_APPROVED) {
            return back()->with('error', 'Afiliação não está ativa.');
        }

        $enrollment->update(['status' => ProductAffiliateEnrollment::STATUS_REVOKED]);

        return back()->with('success', 'Afiliação revogada (bloqueada).');
    }

    private function authorizeEnrollment(ProductAffiliateEnrollment $enrollment): Product
    {
        $enrollment->loadMissing('product');
        $product = $enrollment->product;
        if ($product === null || (string) $product->tenant_id !== (string) auth()->user()->tenant_id) {
            abort(403);
        }

        if (auth()->user()->isTeam()) {
            $allowed = app(TeamAccessService::class)->allowedProductIdsFor(auth()->user());
            if (! in_array($product->id, $allowed, true)) {
                abort(403);
            }
        }

        return $product;
    }
}
