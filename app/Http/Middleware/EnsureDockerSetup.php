<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\DockerSetupState;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDockerSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! DockerSetupState::isDocker()) {
            return $next($request);
        }

        if (DockerSetupState::isSetupDone()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return $next($request);
        }

        if ($request->is('docker-setup') || $request->is('docker-setup/*')
            || $request->is('install') || $request->is('install/*')
            || $request->is('up')
            || $request->is('manifest.json') || $request->is('painel-sw.js')
            || $request->is('build/*') || $request->is('storage/*')) {
            return $next($request);
        }

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        return redirect('/docker-setup', 302);
    }
}
