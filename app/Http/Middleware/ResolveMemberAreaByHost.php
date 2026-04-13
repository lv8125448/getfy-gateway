<?php

namespace App\Http\Middleware;

use App\Services\MemberAreaResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve member area product when access is by subdomain or custom domain (host-based).
 * Used by routes that serve the member area at the root path (e.g. /manifest.json, /) on those hosts.
 */
class ResolveMemberAreaByHost
{
    public function __construct(
        protected MemberAreaResolver $resolver
    ) {}

    /**
     * @param  \Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resolved = $this->resolver->resolve($request);
        if (! $resolved) {
            abort(404, 'Área de membros não encontrada.');
        }
        $request->attributes->set('member_area_product', $resolved['product']);
        $request->attributes->set('member_area_access_type', $resolved['access_type']);
        $request->attributes->set('member_area_slug', $resolved['slug']);
        $request->route()?->setParameter('product', $resolved['product']);
        $request->route()?->setParameter('slug', $resolved['slug']);

        return $next($request);
    }
}
