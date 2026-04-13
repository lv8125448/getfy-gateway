<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductAffiliateEnrollment;
use App\Services\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateProductPanelController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $rates = config('products.rates', ['brl_eur' => 0.16, 'brl_usd' => 0.18]);

        $enrollments = ProductAffiliateEnrollment::query()
            ->where('affiliate_user_id', $user->id)
            ->whereIn('status', [
                ProductAffiliateEnrollment::STATUS_APPROVED,
                ProductAffiliateEnrollment::STATUS_PENDING,
            ])
            ->with(['product' => function ($q) {
                $q->select([
                    'id', 'tenant_id', 'name', 'slug', 'type', 'billing_type', 'price', 'currency',
                    'is_active', 'image', 'checkout_slug',
                ]);
            }])
            ->orderByRaw("case status when 'approved' then 0 when 'pending' then 1 else 2 end")
            ->orderByDesc('updated_at')
            ->get();

        $storage = app(StorageService::class);
        $items = [];
        foreach ($enrollments as $e) {
            $p = $e->product;
            if (! $p || ! $p->is_active) {
                continue;
            }
            // Opcional: não listar como "afiliado" o próprio produto da conta (dono = mesmo tenant)
            if ((string) $p->tenant_id === (string) $user->tenant_id) {
                continue;
            }
            $imageUrl = $p->image ? $storage->url($p->image) : null;
            $checkoutUrl = $p->checkout_slug ? url('/c/'.$p->checkout_slug) : '';
            $link = $e->public_ref && $checkoutUrl
                ? $checkoutUrl.(str_contains($checkoutUrl, '?') ? '&' : '?').'ref='.urlencode((string) $e->public_ref)
                : null;

            $priceBrl = (float) $p->price;
            $currency = $p->currency ?? 'BRL';
            if ($currency !== 'BRL') {
                $priceBrl = $currency === 'EUR' ? $priceBrl / ($rates['brl_eur'] ?? 0.16) : $priceBrl / ($rates['brl_usd'] ?? 0.18);
            }

            $items[] = [
                'enrollment_id' => $e->id,
                'status' => $e->status,
                'product_id' => $p->id,
                'name' => $p->name,
                'type' => $p->type,
                'type_label' => Product::typeConfig()[$p->type]['label'] ?? $p->type,
                'billing_type_label' => Product::billingTypeLabels()[$p->billing_type] ?? $p->billing_type,
                'price_brl' => round($priceBrl, 2),
                'currency' => $currency,
                'is_active' => (bool) $p->is_active,
                'image_url' => $imageUrl,
                'checkout_slug' => $p->checkout_slug,
                'affiliate_link' => $link,
                'public_ref' => $e->public_ref,
            ];
        }

        return Inertia::render('Produtos/Afiliados', [
            'affiliate_products' => $items,
            'exchange_rates' => $rates,
        ]);
    }

    public function show(Request $request, Product $produto): Response
    {
        $user = $request->user();
        $enrollment = $this->approvedEnrollmentOrAbort($user->id, $produto);

        $checkoutBaseUrl = $produto->checkout_slug ? url('/c/'.$produto->checkout_slug) : '';
        $affiliateLink = $enrollment->public_ref && $checkoutBaseUrl
            ? $checkoutBaseUrl.(str_contains($checkoutBaseUrl, '?') ? '&' : '?').'ref='.urlencode((string) $enrollment->public_ref)
            : null;

        $storage = app(StorageService::class);
        $imageUrl = $produto->image ? $storage->url($produto->image) : null;

        return Inertia::render('Produtos/PainelAfiliado', [
            'produto' => [
                'id' => $produto->id,
                'name' => $produto->name,
                'image_url' => $imageUrl,
                'checkout_slug' => $produto->checkout_slug,
            ],
            'enrollment' => [
                'id' => $enrollment->id,
                'public_ref' => $enrollment->public_ref,
                'affiliate_link' => $affiliateLink,
                'conversion_pixels' => $enrollment->conversion_pixels ?? Product::defaultConversionPixels(),
            ],
        ]);
    }

    public function updatePixels(Request $request, Product $produto): RedirectResponse
    {
        $user = $request->user();
        $enrollment = $this->approvedEnrollmentOrAbort($user->id, $produto);

        $validated = $request->validate([
            'conversion_pixels' => ['required', 'array'],
        ]);

        $json = json_encode($validated['conversion_pixels']);
        if ($json === false || strlen($json) > 65535) {
            return back()->withErrors(['conversion_pixels' => 'Dados de pixels inválidos ou muito grandes.'])->withInput();
        }

        $enrollment->conversion_pixels = $validated['conversion_pixels'];
        $enrollment->save();

        return redirect()
            ->route('produtos.painel-afiliado.show', $produto)
            ->with('success', 'Pixels de afiliado atualizados.');
    }

    private function approvedEnrollmentOrAbort(int $userId, Product $produto): ProductAffiliateEnrollment
    {
        $enrollment = ProductAffiliateEnrollment::query()
            ->where('product_id', $produto->id)
            ->where('affiliate_user_id', $userId)
            ->where('status', ProductAffiliateEnrollment::STATUS_APPROVED)
            ->first();

        if ($enrollment === null) {
            abort(403, 'Sem permissão para este painel de afiliado.');
        }

        return $enrollment;
    }
}
