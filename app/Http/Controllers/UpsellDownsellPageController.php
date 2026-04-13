<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UpsellDownsellPageController extends Controller
{
    public function editUpsellPage(Product $produto): Response
    {
        $this->authorizeProduct($produto);

        $defaults = Product::defaultCheckoutConfig();
        $upsell = array_replace_recursive($defaults['upsell'] ?? [], $produto->checkout_config['upsell'] ?? []);

        return Inertia::render('Checkout/UpsellPageEditor', [
            'produto' => [
                'id' => $produto->id,
                'name' => $produto->name,
            ],
            'config' => $upsell,
            'products_for_upsell' => $this->getProductsForUpsell($produto),
            'type' => 'upsell',
            'layoutFullWidth' => true,
        ]);
    }

    public function editDownsellPage(Product $produto): Response
    {
        $this->authorizeProduct($produto);

        $defaults = Product::defaultCheckoutConfig();
        $downsell = array_replace_recursive($defaults['downsell'] ?? [], $produto->checkout_config['downsell'] ?? []);

        return Inertia::render('Checkout/DownsellPageEditor', [
            'produto' => [
                'id' => $produto->id,
                'name' => $produto->name,
            ],
            'config' => $downsell,
            'products_for_upsell' => $this->getProductsForUpsell($produto),
            'type' => 'downsell',
            'layoutFullWidth' => true,
        ]);
    }

    public function updateUpsellPage(Request $request, Product $produto): RedirectResponse|JsonResponse
    {
        $this->authorizeProduct($produto);

        $this->mergeJsonBodyIntoRequest($request);
        $this->normalizeUpsellConfigPayload($request);

        $request->validate([
            'config' => ['required', 'array'],
            'config.enabled' => ['boolean'],
            'config.products' => ['array'],
            'config.products.*.product_id' => ['nullable', 'string', 'max:64'],
            'config.products.*.product_offer_id' => ['nullable', 'integer'],
            'config.products.*.title_override' => ['nullable', 'string', 'max:500'],
            'config.products.*.description' => ['nullable', 'string', 'max:5000'],
            'config.products.*.image_url' => ['nullable', 'string', 'max:500'],
            'config.products.*.video_url' => ['nullable', 'string', 'max:500'],
            'config.page' => ['nullable', 'array'],
            'config.appearance' => ['nullable', 'array'],
        ]);

        $defaults = Product::defaultCheckoutConfig();
        $current = $produto->checkout_config ?? [];
        $payload = $request->input('config') ?? $request->json('config');
        $newUpsell = array_replace_recursive($defaults['upsell'] ?? [], is_array($payload) ? $payload : []);
        $current['upsell'] = $newUpsell;
        $produto->update(['checkout_config' => $current]);

        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['success' => true, 'message' => 'Página de upsell atualizada.']);
        }

        return back()->with('success', 'Página de upsell atualizada.');
    }

    public function updateDownsellPage(Request $request, Product $produto): RedirectResponse|JsonResponse
    {
        $this->authorizeProduct($produto);

        $this->mergeJsonBodyIntoRequest($request);
        $this->normalizeDownsellConfigPayload($request);

        $request->validate([
            'config' => ['required', 'array'],
            'config.enabled' => ['sometimes', 'boolean'],
            'config.product_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'config.product_offer_id' => ['sometimes', 'nullable', 'integer'],
            'config.title_override' => ['sometimes', 'nullable', 'string', 'max:500'],
            'config.description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'config.image_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'config.video_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'config.page' => ['sometimes', 'nullable', 'array'],
            'config.appearance' => ['sometimes', 'nullable', 'array'],
        ]);

        $defaults = Product::defaultCheckoutConfig();
        $current = $produto->checkout_config ?? [];
        $payload = $request->input('config') ?? $request->json('config');
        $newDownsell = array_replace_recursive($defaults['downsell'] ?? [], is_array($payload) ? $payload : []);
        if (!($current['upsell']['enabled'] ?? false)) {
            $newDownsell['enabled'] = false;
        }
        $current['downsell'] = $newDownsell;
        $produto->update(['checkout_config' => $current]);

        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['success' => true, 'message' => 'Página de downsell atualizada.']);
        }

        return back()->with('success', 'Página de downsell atualizada.');
    }

    private function mergeJsonBodyIntoRequest(Request $request): void
    {
        $content = $request->getContent();
        if ($content && is_string($content) && $request->header('Content-Type') && str_contains($request->header('Content-Type'), 'application/json')) {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $request->merge($decoded);
            }
        }
    }

    /**
     * Normaliza tipos do payload de upsell (product_id como string, product_offer_id como int)
     * para evitar falha de validação quando o frontend envia número ou outro tipo.
     */
    private function normalizeUpsellConfigPayload(Request $request): void
    {
        $config = $request->input('config');
        if (! is_array($config) || ! isset($config['products']) || ! is_array($config['products'])) {
            return;
        }
        foreach ($config['products'] as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            if (array_key_exists('product_id', $item)) {
                $v = $item['product_id'];
                if ($v === null || $v === '') {
                    $config['products'][$i]['product_id'] = null;
                } else {
                    $config['products'][$i]['product_id'] = (string) $v;
                }
            }
            if (array_key_exists('product_offer_id', $item)) {
                $v = $item['product_offer_id'];
                if ($v === null || $v === '') {
                    $config['products'][$i]['product_offer_id'] = null;
                } else {
                    $config['products'][$i]['product_offer_id'] = (int) $v;
                }
            }
            foreach (['title_override', 'description', 'image_url', 'video_url'] as $key) {
                if (array_key_exists($key, $item) && $item[$key] !== null && ! is_string($item[$key])) {
                    $config['products'][$i][$key] = (string) $item[$key];
                }
            }
        }
        $this->normalizePageAndAppearance($config);
        $request->merge(['config' => $config]);
    }

    /**
     * Garante que chaves conhecidas de page e appearance sejam string quando aplicável.
     */
    private function normalizePageAndAppearance(array &$config): void
    {
        $stringKeys = [
            'page' => ['headline', 'subheadline', 'body_text', 'hero_video_url', 'background_color'],
            'appearance' => ['title', 'subtitle', 'primary_color', 'button_accept', 'button_decline'],
        ];
        foreach ($stringKeys as $section => $keys) {
            if (! isset($config[$section]) || ! is_array($config[$section])) {
                continue;
            }
            foreach ($keys as $key) {
                if (array_key_exists($key, $config[$section]) && $config[$section][$key] !== null && ! is_string($config[$section][$key])) {
                    $config[$section][$key] = (string) $config[$section][$key];
                }
            }
        }
    }

    /**
     * Normaliza tipos do payload de downsell.
     */
    private function normalizeDownsellConfigPayload(Request $request): void
    {
        $config = $request->input('config');
        if (! is_array($config)) {
            return;
        }
        if (array_key_exists('product_id', $config)) {
            $v = $config['product_id'];
            if ($v === null || $v === '') {
                $config['product_id'] = null;
            } else {
                $config['product_id'] = (string) $v;
            }
        }
        if (array_key_exists('product_offer_id', $config)) {
            $v = $config['product_offer_id'];
            if ($v === null || $v === '') {
                $config['product_offer_id'] = null;
            } else {
                $config['product_offer_id'] = (int) $v;
            }
        }
        foreach (['title_override', 'description', 'image_url', 'video_url'] as $key) {
            if (array_key_exists($key, $config) && $config[$key] !== null && ! is_string($config[$key])) {
                $config[$key] = (string) $config[$key];
            }
        }
        $this->normalizePageAndAppearance($config);
        $request->merge(['config' => $config]);
    }

    private function getProductsForUpsell(Product $excludeProduct): array
    {
        $tenantId = auth()->user()->tenant_id;

        return Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('id', '!=', $excludeProduct->id)
            ->with('offers')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'offers' => $p->offers->map(fn (ProductOffer $o) => [
                    'id' => $o->id,
                    'name' => $o->name,
                    'price' => (float) $o->price,
                ])->values()->all(),
            ])
            ->values()->all();
    }

    private function authorizeProduct(Product $produto): void
    {
        $tenantId = auth()->user()->tenant_id;
        if ($produto->tenant_id !== $tenantId) {
            abort(403);
        }
    }
}
