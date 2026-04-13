<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ForceHttpsWhenForwardedProto
{
    public function handle(Request $request, Closure $next): Response
    {
        $proto = strtolower((string) $request->headers->get('x-forwarded-proto', ''));
        $isHttpsForwarded = str_contains($proto, 'https');
        if (! $isHttpsForwarded) {
            $cfVisitor = strtolower((string) $request->headers->get('cf-visitor', ''));
            $isHttpsForwarded = str_contains($cfVisitor, 'https');
        }

        if ($isHttpsForwarded) {
            URL::forceScheme('https');
            $request->server->set('HTTPS', 'on');
            $request->server->set('SERVER_PORT', '443');
        }

        return $next($request);
    }
}

