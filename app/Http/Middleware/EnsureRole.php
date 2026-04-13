<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403, 'Acesso não autorizado.');
        }

        $allowed = str_contains($role, '|')
            ? explode('|', $role)
            : [$role];

        if (! in_array($user->role, $allowed, true)) {
            abort(403, 'Acesso não autorizado.');
        }

        return $next($request);
    }
}
