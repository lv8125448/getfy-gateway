<?php

namespace App\Http\Middleware;

use App\Services\PlatformI18nService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPanelLocale
{
    public function __construct(private readonly PlatformI18nService $i18n)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ($user->canAccessSellerPanel() || $user->canAccessPlatformPanel())) {
            app()->setLocale($this->i18n->resolveLocale($request));
        }

        return $next($request);
    }
}
