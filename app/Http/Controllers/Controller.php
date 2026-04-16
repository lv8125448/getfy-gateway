<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Remove a URL "intended" quando é só a listagem /area-membros, para o pós-login ir ao painel do cliente.
     */
    protected function forgetAreaMembrosHomeIntended(Request $request): void
    {
        $intended = $request->session()->get('url.intended');
        if (! is_string($intended) || $intended === '') {
            return;
        }
        $path = parse_url($intended, PHP_URL_PATH);
        if ($path === null || $path === '') {
            $path = strtok($intended, '?') ?: $intended;
            if (! str_starts_with($path, '/')) {
                $path = '/'.$path;
            }
        }
        if ($path === '/area-membros' || $path === '/area-membros/') {
            $request->session()->forget('url.intended');
        }
    }
}
