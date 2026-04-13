<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TenantMailConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ForgotPasswordController extends Controller
{
    public function __construct(
        protected TenantMailConfigService $mailConfig
    ) {}

    public function showLinkRequestForm(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Usar o SMTP configurado nas Configurações (E-mail) em vez do .env
        $this->mailConfig->applyMailerConfigForTenant(null);
        config(['mail.default' => 'smtp']);

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (Throwable $e) {
            Log::error('ForgotPassword: falha ao enviar link de redefinição.', [
                'email' => $request->input('email'),
                'message' => $e->getMessage(),
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
            ]);

            $message = 'Não foi possível enviar o e-mail. Verifique as configurações de SMTP em Configurações > E-mail ou tente novamente mais tarde.';
            if (config('app.debug')) {
                $message .= ' Detalhe: '.$e->getMessage();
            }

            return back()->withErrors([
                'email' => [$message],
            ])->onlyInput('email');
        }

        // Não revelar se o e-mail existe ou não (evita enumeração de usuários)
        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors([
                'email' => ['Por favor, aguarde um minuto antes de solicitar um novo link de redefinição de senha.'],
            ])->onlyInput('email');
        }

        return back()->with('status', 'Se o e-mail estiver cadastrado, você receberá o link de redefinição em sua caixa de entrada.');
    }
}
