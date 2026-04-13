<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403, 'Acesso não autorizado.');
        }
        if (! $user->canAccessPlatformPanel()) {
            abort(403, 'Acesso restrito ao operador da plataforma.');
        }

        return $next($request);
    }
}
