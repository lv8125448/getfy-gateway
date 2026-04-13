<?php

namespace App\Http\Middleware;

use App\Models\ApiApplication;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiApplication
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $this->resolveApiKey($request);
        if ($apiKey === null || $apiKey === '') {
            return response()->json(['message' => 'Missing or invalid API key.'], 401);
        }

        $application = $this->findApplicationByKey($apiKey);
        if ($application === null) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        if (! $application->is_active) {
            return response()->json(['message' => 'API application is disabled.'], 403);
        }

        if (! $application->isIpAllowed($request->ip())) {
            return response()->json(['message' => 'IP not allowed.'], 403);
        }

        $request->attributes->set('api_application', $application);
        $request->setUserResolver(fn () => null);

        return $next($request);
    }

    private function resolveApiKey(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if (is_string($header) && str_starts_with(strtolower($header), 'bearer ')) {
            return trim(substr($header, 7));
        }
        return $request->header('X-API-Key');
    }

    private function findApplicationByKey(string $plainKey): ?ApiApplication
    {
        $applications = ApiApplication::active()->get();
        foreach ($applications as $app) {
            if ($app->verifyApiKey($plainKey)) {
                return $app;
            }
        }
        return null;
    }
}
