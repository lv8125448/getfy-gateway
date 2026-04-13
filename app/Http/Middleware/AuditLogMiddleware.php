<?php

namespace App\Http\Middleware;

use App\Models\TeamAuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if (! $user || ! $user->tenant_id) {
            return $response;
        }

        // Evitar logar leituras e recursos estáticos.
        if ($request->isMethod('get') || $request->isMethod('head') || $request->isMethod('options')) {
            return $response;
        }

        // Só registrar quando não deu erro (senão polui com tentativas inválidas).
        $status = method_exists($response, 'getStatusCode') ? (int) $response->getStatusCode() : 200;
        if ($status >= 400) {
            return $response;
        }

        // Não registrar ações de auditoria para não gerar loop/ruído.
        if ($request->is('usuarios/equipe/logs/clear')) {
            return $response;
        }

        $route = $request->route();
        $routeName = $route?->getName();
        $action = is_string($routeName) && $routeName !== '' ? $routeName : 'http.' . strtolower($request->method());

        $targetType = null;
        $targetId = null;
        $params = $route?->parameters() ?? [];
        foreach ($params as $key => $value) {
            if (is_object($value) && method_exists($value, 'getKey')) {
                $targetType = get_class($value);
                $targetId = (string) $value->getKey();
                break;
            }
            if (is_scalar($value) && in_array((string) $key, ['produto', 'product', 'order', 'user', 'member', 'role', 'id'], true)) {
                $targetId = (string) $value;
            }
        }

        TeamAuditLog::create([
            'tenant_id' => $user->tenant_id,
            'actor_user_id' => $user->id,
            'action' => 'panel.' . $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => [
                'method' => strtoupper($request->method()),
                'path' => '/' . ltrim($request->path(), '/'),
            ],
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return $response;
    }
}

