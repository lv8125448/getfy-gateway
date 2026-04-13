<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGuestPlatform
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }
        if ($user->canAccessPlatformPanel()) {
            return redirect()->route('plataforma.dashboard');
        }

        // Não enviar para route('login'): o middleware guest redireciona quem já está logado para /dashboard
        // e a mensagem de erro se perde — o utilizador parece “voltar ao painel do vendedor” sem explicação.
        if ($user->canAccessSellerPanel()) {
            return redirect('/dashboard')->with(
                'error',
                'Você está logado como infoprodutor. Para o painel da plataforma (/plataforma), saia da conta (menu ou /logout) e entre com a conta de operador em /plataforma/login.'
            );
        }

        return redirect('/area-membros')->with(
            'error',
            'Esta conta não acessa o painel da plataforma. Use a conta de operador em /plataforma/login.'
        );
    }
}
