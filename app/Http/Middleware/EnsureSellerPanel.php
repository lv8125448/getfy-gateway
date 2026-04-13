<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSellerPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403, 'Acesso não autorizado.');
        }
        if ($user->canAccessPlatformPanel()) {
            return redirect()
                ->route('plataforma.dashboard')
                ->with('error', 'Use o painel da plataforma.');
        }
        if (! $user->canAccessSellerPanel()) {
            abort(403, 'Acesso não autorizado.');
        }

        if ($user->sellerAccountAccessBlocked()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Conta suspensa ou bloqueada. Contate o suporte.']);
        }

        return $next($request);
    }
}
