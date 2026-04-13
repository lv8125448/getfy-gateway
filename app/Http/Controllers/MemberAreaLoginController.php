<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MemberAreaLoginController extends Controller
{
    public function showLoginForm(Request $request, string $slug): Response|RedirectResponse
    {
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product || $product->type !== Product::TYPE_AREA_MEMBROS) {
            abort(404, 'Área de membros não encontrada.');
        }
        $slug = $request->route('slug') ?? $request->attributes->get('member_area_slug') ?? $slug;
        if (Auth::check() && $product->hasMemberAreaAccess(Auth::user())) {
            return redirect()->route('member-area-app.show', ['slug' => $slug]);
        }
        $config = $product->member_area_config;
        $loginConfig = $config['login'] ?? [];
        return Inertia::render('MemberAreaApp/Login', [
            'slug' => $slug,
            'product' => [
                'name' => $product->name,
                'logo_light' => $loginConfig['logo'] ?? ($config['logos']['logo_light'] ?? ''),
                'logo_dark' => $config['logos']['logo_dark'] ?? '',
                'title' => $loginConfig['title'] ?? 'Área de Membros',
                'subtitle' => $loginConfig['subtitle'] ?? 'Entre com seu e-mail e senha',
                'background_image' => $loginConfig['background_image'] ?? '',
                'background_color' => $loginConfig['background_color'] ?? '#18181b',
                'primary_color' => $loginConfig['primary_color'] ?? '#0ea5e9',
                'login_without_password' => (bool) ($loginConfig['login_without_password'] ?? false),
                'login_without_password_url' => ! empty($loginConfig['login_without_password'])
                    ? ($request->route('slug') !== null ? url('/m/' . $slug . '/login-without-password') : url('/login-without-password'))
                    : null,
            ],
        ]);
    }

    public function login(Request $request, string $slug): RedirectResponse
    {
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product || $product->type !== Product::TYPE_AREA_MEMBROS) {
            abort(404, 'Área de membros não encontrada.');
        }
        $slug = $request->route('slug') ?? $request->attributes->get('member_area_slug') ?? $slug;
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Credenciais inválidas.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        if (! $product->hasMemberAreaAccess(Auth::user())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['email' => 'Você não tem acesso a esta área.'])->onlyInput('email');
        }
        return redirect()->intended(route('member-area-app.show', ['slug' => $slug]));
    }

    public function loginWithoutPassword(Request $request, string $slug): RedirectResponse
    {
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product || $product->type !== Product::TYPE_AREA_MEMBROS) {
            abort(404, 'Área de membros não encontrada.');
        }
        $slug = $request->route('slug') ?? $request->attributes->get('member_area_slug') ?? $slug;
        $loginConfig = $product->member_area_config['login'] ?? [];
        if (empty($loginConfig['login_without_password'])) {
            return back()->withErrors(['email' => 'Login apenas com e-mail não está habilitado para esta área.'])->onlyInput('email');
        }
        $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $request->input('email'))->first();
        if (! $user || $user->canAccessPanel()) {
            return back()->withErrors(['email' => 'Credenciais inválidas.'])->onlyInput('email');
        }
        if (! $product->hasMemberAreaAccess($user)) {
            return back()->withErrors(['email' => 'Credenciais inválidas.'])->onlyInput('email');
        }
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('member-area-app.show', ['slug' => $slug]));
    }

    public function magicAccess(Request $request, string $slug): RedirectResponse
    {
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product || $product->type !== Product::TYPE_AREA_MEMBROS) {
            abort(404, 'Área de membros não encontrada.');
        }
        $slug = $request->route('slug') ?? $request->attributes->get('member_area_slug') ?? $slug;
        $userId = (int) $request->query('u', 0);
        $user = $userId > 0 ? User::find($userId) : null;
        if (! $user || ! $product->hasMemberAreaAccess($user)) {
            return redirect()->route('member-area.login', ['slug' => $slug])->with('error', 'Link inválido ou expirado.');
        }
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->intended(route('member-area-app.show', ['slug' => $slug]));
    }

    public function magicAccessHost(Request $request): RedirectResponse
    {
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product || $product->type !== Product::TYPE_AREA_MEMBROS) {
            abort(404, 'Área de membros não encontrada.');
        }
        $userId = (int) $request->query('u', 0);
        $user = $userId > 0 ? User::find($userId) : null;
        if (! $user || ! $product->hasMemberAreaAccess($user)) {
            return redirect()->to('/login')->with('error', 'Link inválido ou expirado.');
        }
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->to('/');
    }
}
