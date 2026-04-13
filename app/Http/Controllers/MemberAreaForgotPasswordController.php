<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\TenantMailConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MemberAreaForgotPasswordController extends Controller
{
    public function __construct(
        protected TenantMailConfigService $mailConfig
    ) {}

    public function showLinkRequestForm(Request $request, string $slug): Response
    {
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product || $product->type !== Product::TYPE_AREA_MEMBROS) {
            abort(404, 'Área de membros não encontrada.');
        }
        $config = $product->member_area_config;
        $loginConfig = $config['login'] ?? [];
        return Inertia::render('MemberAreaApp/ForgotPassword', [
            'slug' => $slug,
            'product' => [
                'name' => $product->name,
                'logo_light' => $loginConfig['logo'] ?? ($config['logos']['logo_light'] ?? ''),
                'title' => $loginConfig['title'] ?? 'Área de Membros',
                'primary_color' => $loginConfig['primary_color'] ?? '#0ea5e9',
                'background_image' => $loginConfig['background_image'] ?? '',
                'background_color' => $loginConfig['background_color'] ?? '#18181b',
            ],
        ]);
    }

    public function sendResetLinkEmail(Request $request, string $slug): RedirectResponse
    {
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product || $product->type !== Product::TYPE_AREA_MEMBROS) {
            abort(404, 'Área de membros não encontrada.');
        }
        $request->validate(['email' => ['required', 'email']]);

        $redirect = '/m/'.$slug.'/login';
        app()->instance('password_reset_redirect', $redirect);

        $this->mailConfig->applyMailerConfigForTenant(null);
        config(['mail.default' => 'smtp']);

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (Throwable $e) {
            Log::error('MemberAreaForgotPassword: falha ao enviar link de redefinição.', [
                'slug' => $slug,
                'email' => $request->input('email'),
                'message' => $e->getMessage(),
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
            ]);
            $message = 'Não foi possível enviar o e-mail. Tente novamente mais tarde.';
            if (config('app.debug')) {
                $message .= ' Detalhe: '.$e->getMessage();
            }
            return back()->withErrors(['email' => [$message]])->onlyInput('email');
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors([
                'email' => ['Por favor, aguarde um minuto antes de solicitar um novo link de redefinição de senha.'],
            ])->onlyInput('email');
        }

        return back()->with('status', 'Se o e-mail estiver cadastrado, você receberá o link de redefinição em sua caixa de entrada.');
    }
}
