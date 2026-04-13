<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventCacheForHtml
{
    /**
     * Evita cache do documento HTML (login e demais páginas Inertia).
     * Assim o token CSRF na meta tag não fica desatualizado e o primeiro login não retorna 419.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->headers->get('Content-Type') && str_contains($response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
