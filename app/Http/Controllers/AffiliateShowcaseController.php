<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductAffiliateEnrollment;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateShowcaseController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $search = trim((string) $request->query('q', ''));
        $type = $request->query('type');
        $sort = $request->query('sort', 'hot');

        $query = Product::query()
            ->where('affiliate_enabled', true)
            ->where('affiliate_show_in_showcase', true)
            ->where('is_active', true)
            ->withCount([
                'orderBumps',
                'orders as sales_count' => fn ($q) => $q->where('orders.status', 'completed'),
            ]);

        if ($search !== '') {
            $query->where('products.name', 'like', '%'.$search.'%');
        }

        if (is_string($type) && $type !== '' && $type !== 'all') {
            $query->where('products.type', $type);
        }

        if ($sort === 'name') {
            $query->orderBy('products.name');
        } elseif ($sort === 'price_asc') {
            $query->orderBy('products.price');
        } elseif ($sort === 'price_desc') {
            $query->orderByDesc('products.price');
        } else {
            $query->orderByDesc('sales_count')->orderBy('products.name');
        }

        $products = $query->paginate(24)->withQueryString();

        $ownerIds = $products->getCollection()->pluck('tenant_id')->unique()->filter()->all();
        $owners = User::query()->whereIn('id', $ownerIds)->get(['id', 'name'])->keyBy('id');

        $productIds = $products->pluck('id')->all();
        $enrollmentByProduct = [];
        if ($productIds !== []) {
            $enrollments = ProductAffiliateEnrollment::query()
                ->where('affiliate_user_id', $user->id)
                ->whereIn('product_id', $productIds)
                ->get();
            foreach ($enrollments as $e) {
                $enrollmentByProduct[$e->product_id] = $e;
            }
        }

        $storage = app(StorageService::class);

        $products->through(function (Product $p) use ($storage, $owners, $enrollmentByProduct, $user) {
            $owner = $owners->get($p->tenant_id);
            $enrollment = $enrollmentByProduct[$p->id] ?? null;
            $isOwnProduct = (string) $p->tenant_id === (string) $user->tenant_id;

            return self::showcaseProductPayload($p, $storage, $owner, $enrollment, $isOwnProduct);
        });

        $types = collect(Product::typeConfig())->map(fn ($c, $v) => [
            'value' => $v,
            'label' => $c['label'],
        ])->values()->all();

        return Inertia::render('Produtos/VitrineAfiliacao', [
            'products' => $products,
            'filters' => [
                'q' => $search,
                'type' => is_string($type) ? $type : '',
                'sort' => is_string($sort) ? $sort : 'hot',
            ],
            'product_types' => $types,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function showcaseProductPayload(
        Product $p,
        StorageService $storage,
        ?User $owner,
        ?ProductAffiliateEnrollment $enrollment,
        bool $isOwnProduct = false,
    ): array {
        $imageUrl = $p->image ? $storage->url($p->image) : null;
        $commissionPct = (float) $p->affiliate_commission_percent;
        $price = (float) $p->price;
        $commissionMax = round($price * $commissionPct / 100.0, 2);

        $checkoutUrl = $p->checkout_slug
            ? url('/c/'.$p->checkout_slug)
            : '';

        return [
            'id' => $p->id,
            'name' => $p->name,
            'type' => $p->type,
            'type_label' => Product::typeConfig()[$p->type]['label'] ?? $p->type,
            'image_url' => $imageUrl,
            'price' => $price,
            'currency' => $p->currency ?? 'BRL',
            'sales_count' => (int) ($p->sales_count ?? 0),
            'affiliate_commission_percent' => $commissionPct,
            'commission_max_formatted' => number_format($commissionMax, 2, ',', '.'),
            'affiliate_page_url' => $p->affiliate_page_url,
            'affiliate_support_email' => $p->affiliate_support_email,
            'affiliate_showcase_description' => $p->affiliate_showcase_description,
            'affiliate_manual_approval' => (bool) $p->affiliate_manual_approval,
            'producer_name' => $owner?->name ?? '—',
            'checkout_url' => $checkoutUrl,
            'order_bumps_count' => (int) ($p->order_bumps_count ?? 0),
            'is_own_product' => $isOwnProduct,
            'enrollment' => $enrollment ? [
                'status' => $enrollment->status,
                'public_ref' => $enrollment->public_ref,
                'affiliate_link' => $enrollment->public_ref && $checkoutUrl
                    ? $checkoutUrl.(str_contains($checkoutUrl, '?') ? '&' : '?').'ref='.urlencode($enrollment->public_ref)
                    : null,
            ] : null,
        ];
    }

    public function enroll(Request $request, Product $product): \Illuminate\Http\RedirectResponse
    {
        $user = auth()->user();

        if ($product->tenant_id === $user->tenant_id) {
            return back()->with('error', 'Você não pode se afiliar ao próprio produto.');
        }

        if (! $product->affiliate_enabled || ! $product->affiliate_show_in_showcase || ! $product->is_active) {
            return back()->with('error', 'Este produto não está disponível para afiliação.');
        }

        if (! $product->affiliateCommissionTotalsValid()) {
            return back()->with('error', 'Este produto não pode aceitar afiliados no momento (comissões inválidas).');
        }

        $enrollment = ProductAffiliateEnrollment::query()
            ->where('product_id', $product->id)
            ->where('affiliate_user_id', $user->id)
            ->first();

        if ($enrollment) {
            if ($enrollment->status === ProductAffiliateEnrollment::STATUS_APPROVED) {
                return back()->with('info', 'Você já é afiliado deste produto.');
            }
            if ($enrollment->status === ProductAffiliateEnrollment::STATUS_PENDING) {
                return back()->with('info', 'Sua solicitação já está pendente.');
            }
            if (in_array($enrollment->status, [ProductAffiliateEnrollment::STATUS_REJECTED, ProductAffiliateEnrollment::STATUS_REVOKED], true)) {
                $enrollment->update([
                    'status' => ProductAffiliateEnrollment::STATUS_PENDING,
                    'public_ref' => null,
                ]);
            }
        } else {
            $enrollment = ProductAffiliateEnrollment::query()->create([
                'product_id' => $product->id,
                'affiliate_user_id' => $user->id,
                'status' => ProductAffiliateEnrollment::STATUS_PENDING,
                'public_ref' => null,
            ]);
        }

        if (! $product->affiliate_manual_approval) {
            $enrollment->refresh();
            $enrollment->update(['status' => ProductAffiliateEnrollment::STATUS_APPROVED]);
            $enrollment->ensurePublicRef();
        }

        return back()->with('success', $product->affiliate_manual_approval
            ? 'Solicitação enviada ao produtor.'
            : 'Você foi aprovado como afiliado.');
    }
}
