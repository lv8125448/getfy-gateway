<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\TeamAuditLog;
use App\Services\MemberAreaResolver;
use App\Support\DockerSetupState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Exibe o login da plataforma ou, se o host for de área de membros (subdomínio/domínio próprio),
     * delega para o login da área de membros do produto.
     */
    public function showLoginForm(Request $request): Response|RedirectResponse
    {
        if (DockerSetupState::isDocker() && ! DockerSetupState::isSetupDone()) {
            return redirect('/docker-setup');
        }

        if (User::count() === 0) {
            return redirect()->route('criar-admin');
        }

        $resolved = app(MemberAreaResolver::class)->resolve($request);
        if ($resolved && in_array($resolved['access_type'], ['subdomain', 'custom'], true)) {
            $request->attributes->set('member_area_product', $resolved['product']);
            $request->attributes->set('member_area_access_type', $resolved['access_type']);
            $request->attributes->set('member_area_slug', $resolved['slug']);

            return app()->call(\App\Http\Controllers\MemberAreaLoginController::class.'@showLoginForm', [
                'request' => $request,
                'slug' => $resolved['slug'],
            ]);
        }

        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        if (DockerSetupState::isDocker() && ! DockerSetupState::isSetupDone()) {
            return redirect('/docker-setup');
        }

        $resolved = app(MemberAreaResolver::class)->resolve($request);
        if ($resolved && in_array($resolved['access_type'], ['subdomain', 'custom'], true)) {
            $request->attributes->set('member_area_product', $resolved['product']);
            $request->attributes->set('member_area_access_type', $resolved['access_type']);
            $request->attributes->set('member_area_slug', $resolved['slug']);

            return app()->call(\App\Http\Controllers\MemberAreaLoginController::class.'@login', [
                'request' => $request,
                'slug' => $resolved['slug'],
            ]);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, (bool) $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();
            if ($user && $user->canAccessPlatformPanel()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'Credenciais inválidas.',
                ])->onlyInput('email');
            }
            if ($user && $user->tenant_id && $user->canAccessSellerPanel()) {
                TeamAuditLog::create([
                    'tenant_id' => $user->tenant_id,
                    'actor_user_id' => $user->id,
                    'action' => 'auth.login',
                    'metadata' => [
                        'method' => 'POST',
                        'path' => '/login',
                    ],
                    'ip' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                ]);
            }
            if ($user->canAccessSellerPanel()) {
                if ($user->sellerAccountAccessBlocked()) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return back()->withErrors([
                        'email' => 'Conta suspensa ou bloqueada. Contate o suporte.',
                    ])->onlyInput('email');
                }

                $request->session()->put('panel_context', 'seller');

                return redirect()->intended('/dashboard');
            }

            if ($user->canAccessCustomerPanel()) {
                $request->session()->put('panel_context', 'customer');
                $this->forgetAreaMembrosHomeIntended($request);

                return redirect()->intended('/painel-cliente');
            }

            return redirect()->intended('/area-membros');
        }

        return back()->withErrors([
            'email' => 'Credenciais inválidas.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user && $user->tenant_id && $user->canAccessSellerPanel()) {
            TeamAuditLog::create([
                'tenant_id' => $user->tenant_id,
                'actor_user_id' => $user->id,
                'action' => 'auth.logout',
                'metadata' => [
                    'method' => 'POST',
                    'path' => '/logout',
                ],
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $to = $request->query('redirect');
        if (is_string($to) && $this->isSafeMemberAreaLoginRedirect($to)) {
            return redirect($to);
        }

        return redirect('/');
    }

    /**
     * Evita open redirect: só paths de login da área de membros (/m/{slug}/login ou /login em host dedicado).
     */
    private function isSafeMemberAreaLoginRedirect(string $path): bool
    {
        if ($path === '' || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return false;
        }
        if (str_contains($path, '..')) {
            return false;
        }

        return (bool) preg_match('#^/m/[a-zA-Z0-9]{6,16}/login$#', $path)
            || $path === '/login';
    }
}
