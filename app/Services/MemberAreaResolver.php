<?php

namespace App\Services;

use App\Models\MemberAreaDomain;
use App\Models\Product;
use Illuminate\Http\Request;

class MemberAreaResolver
{
    /**
     * Resolve product and access type from request (path, subdomain, or custom domain).
     *
     * @return array{product: Product, access_type: string, slug: string|null}|null
     */
    public function resolve(Request $request): ?array
    {
        $hostRaw = strtolower(rtrim(trim($request->getHost()), '.'));
        $hostNormalized = MemberAreaDomain::normalizeCustomHost($hostRaw);
        $hosts = array_values(array_unique(array_filter([$hostRaw, $hostNormalized])));
        $path = $request->path();

        // Custom domain: host matches a stored custom domain
        $domain = MemberAreaDomain::where('type', MemberAreaDomain::TYPE_CUSTOM)
            ->whereIn('value', $hosts)
            ->with('product')
            ->first();
        if ($domain && $domain->product && $domain->product->type === Product::TYPE_AREA_MEMBROS) {
            return [
                'product' => $domain->product,
                'access_type' => 'custom',
                'slug' => $domain->product->checkout_slug,
            ];
        }

        // Subdomain: {slug}.members.xxx
        if (config('members.subdomain_enabled')) {
            $base = config('members.subdomain_base', '');
            if ($base && str_ends_with($host, $base) && $host !== $base) {
                $prefix = str_replace('.'.$base, '', $host);
                if ($prefix !== $host) {
                    $slug = $prefix;
                    $product = Product::where('checkout_slug', $slug)
                        ->where('type', Product::TYPE_AREA_MEMBROS)
                        ->first();
                    if ($product) {
                        return [
                            'product' => $product,
                            'access_type' => 'subdomain',
                            'slug' => $slug,
                        ];
                    }
                }
            }
        }

        // Path: /m/{slug} — use route parameter when available (reliable with subdirs), else parse path
        $pathSlug = $request->route()?->parameter('slug');
        if ($pathSlug !== null && $pathSlug !== '') {
            $slugNormalized = strtolower((string) $pathSlug);
            $product = Product::where('checkout_slug', $slugNormalized)
                ->where('type', Product::TYPE_AREA_MEMBROS)
                ->first();
            if ($product) {
                return [
                    'product' => $product,
                    'access_type' => 'path',
                    'slug' => $slugNormalized,
                ];
            }
            $pathDomain = MemberAreaDomain::where('type', MemberAreaDomain::TYPE_PATH)
                ->where('value', $slugNormalized)
                ->with('product')
                ->first();
            if ($pathDomain && $pathDomain->product && $pathDomain->product->type === Product::TYPE_AREA_MEMBROS) {
                return [
                    'product' => $pathDomain->product,
                    'access_type' => 'path',
                    'slug' => $slugNormalized,
                ];
            }
        }

        $path = $request->path();
        if (str_starts_with($path, 'm/')) {
            $segments = explode('/', trim($path, '/'));
            $slug = $segments[1] ?? null;
            if ($slug !== null && $slug !== '') {
                $slugNormalized = strtolower($slug);
                $product = Product::where('checkout_slug', $slugNormalized)
                    ->where('type', Product::TYPE_AREA_MEMBROS)
                    ->first();
                if ($product) {
                    return [
                        'product' => $product,
                        'access_type' => 'path',
                        'slug' => $slugNormalized,
                    ];
                }
                $pathDomain = MemberAreaDomain::where('type', MemberAreaDomain::TYPE_PATH)
                    ->where('value', $slugNormalized)
                    ->with('product')
                    ->first();
                if ($pathDomain && $pathDomain->product && $pathDomain->product->type === Product::TYPE_AREA_MEMBROS) {
                    return [
                        'product' => $pathDomain->product,
                        'access_type' => 'path',
                        'slug' => $slugNormalized,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Get the base URL for a product's member area (for links, PWA manifest, etc).
     */
    public function baseUrlForProduct(Product $product): string
    {
        $domain = $product->memberAreaDomain;
        $appUrl = rtrim(config('app.url'), '/');
        $protocol = str_starts_with($appUrl, 'https') ? 'https' : 'http';

        if ($domain) {
            if ($domain->type === MemberAreaDomain::TYPE_CUSTOM && $domain->value) {
                return $protocol.'://'.$domain->value;
            }
            if ($domain->type === MemberAreaDomain::TYPE_SUBDOMAIN && config('members.subdomain_enabled')) {
                $base = config('members.subdomain_base');
                $slug = $domain->value ?: $product->checkout_slug;

                return $protocol.'://'.$slug.'.'.$base;
            }
            if ($domain->type === MemberAreaDomain::TYPE_PATH && $domain->value !== null && $domain->value !== '') {
                return $appUrl.'/m/'.$domain->value;
            }
        }

        return $appUrl.'/m/'.$product->checkout_slug;
    }
}
