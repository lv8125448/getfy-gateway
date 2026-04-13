<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PlatformAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function showLoginForm(): Response
    {
        return Inertia::render('Platform/Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, (bool) $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();
            if (! $user instanceof User || ! $user->canAccessPlatformPanel()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'Credenciais inválidas.',
                ])->onlyInput('email');
            }

            PlatformAuditService::log('platform.auth.login', [
                'email' => $user->email,
            ], $request);

            return redirect()->intended(route('plataforma.dashboard'));
        }

        return back()->withErrors([
            'email' => 'Credenciais inválidas.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($request->user()?->canAccessPlatformPanel()) {
            PlatformAuditService::log('platform.auth.logout', [], $request);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('plataforma.login');
    }
}
