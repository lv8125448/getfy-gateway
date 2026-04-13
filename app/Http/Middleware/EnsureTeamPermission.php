<?php

namespace App\Http\Middleware;

use App\Services\TeamAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403, 'Acesso não autorizado.');
        }

        // Admin/infoprodutor passam (acesso total do tenant).
        if ($user->isAdmin() || $user->isInfoprodutor()) {
            return $next($request);
        }

        // Qualquer outro papel fora equipe não acessa rotas protegidas por permissão.
        if (! $user->isTeam()) {
            abort(403, 'Acesso não autorizado.');
        }

        $access = app(TeamAccessService::class);
        if (! $access->can($user, $permission)) {
            abort(403, 'Acesso não autorizado.');
        }

        return $next($request);
    }
}

