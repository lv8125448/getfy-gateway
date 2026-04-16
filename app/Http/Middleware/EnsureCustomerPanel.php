<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->canAccessCustomerPanel()) {
            abort(403, 'Acesso não autorizado.');
        }

        return $next($request);
    }
}
