<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\ProvidesPlatformGatewayProps;
use App\Http\Controllers\Controller;
use App\Jobs\ReconcileSpacepagWithdrawalJob;
use App\Jobs\ReconcileWooviWithdrawalJob;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\CajuPay\CajuPayPayoutService;
use App\Services\Spacepag\SpacepagPayoutService;
use App\Services\Woovi\WooviPayoutService;
use App\Services\EffectiveMerchantFees;
use App\Services\EffectiveSettlementRules;
use App\Services\MerchantWithdrawalService;
use App\Services\Payout\PayoutUserSettings;
use App\Services\Payout\PlatformPayoutGateway;
use App\Services\PlatformAuditService;
use App\Models\Setting;
use App\Support\PlatformConfigContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialController extends Controller
{
    use ProvidesPlatformGatewayProps;

    public function index(): Response
    {
        $tenantId = PlatformConfigContext::settingsTenantId();

        return Inertia::render('Platform/Financial/Index', [
            'gateways' => $this->buildGatewaysListForSettings($tenantId),
            'gateway_order' => $this->buildGatewayOrderForSettings($tenantId),
            'merchant_fee_rules' => EffectiveMerchantFees::platformDefaults(),
            'merchant_settlement_rules' => EffectiveSettlementRules::platformDefaults(),
            'payout_gateway_preference' => PlatformPayoutGateway::preference(),
            'payout_gateway_active' => PlatformPayoutGateway::activeSlug(),
        ]);
    }

    /**
     * Preferência de saque automático (CajuPay, Spacepag, Woovi ou automático).
     */
    public function updatePayoutGatewayPreference(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preference' => ['required', 'string', 'in:auto,cajupay,spacepag,woovi'],
        ]);

        $pref = $validated['preference'];
        Setting::set('platform_payout_gateway', $pref === 'auto' ? null : $pref, null);

        PlatformAuditService::log('platform.financial.payout_gateway_preference', ['preference' => $pref], $request);

        return response()->json([
            'success' => true,
            'message' => 'Preferência de saque automático atualizada.',
            'payout_gateway_preference' => PlatformPayoutGateway::preference(),
            'payout_gateway_active' => PlatformPayoutGateway::activeSlug(),
        ]);
    }

    public function updateSettlement(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'merchant_settlement_rules' => ['required', 'array'],
        ]);
        foreach (['pix', 'card', 'boleto'] as $key) {
            $request->validate([
                "merchant_settlement_rules.$key" => ['nullable', 'array'],
                "merchant_settlement_rules.$key.days_to_available" => ['nullable', 'integer', 'min:0', 'max:365'],
                "merchant_settlement_rules.$key.reserve_percent" => ['nullable', 'numeric', 'min:0', 'max:100'],
                "merchant_settlement_rules.$key.reserve_hold_days" => ['nullable', 'integer', 'min:0', 'max:365'],
            ]);
        }

        $out = [];
        foreach (['pix', 'card', 'boleto'] as $key) {
            $block = $validated['merchant_settlement_rules'][$key] ?? [];
            $out[$key] = [
                'days_to_available' => max(0, (int) ($block['days_to_available'] ?? 0)),
                'reserve_percent' => round(min(100, max(0, (float) ($block['reserve_percent'] ?? 0))), 2),
                'reserve_hold_days' => max(0, min(365, (int) ($block['reserve_hold_days'] ?? 0))),
            ];
        }

        Setting::set('merchant_settlement_rules', $out, null);

        PlatformAuditService::log('platform.financial.settlement_updated', ['rules' => $out], $request);

        return redirect()->route('plataforma.financeiro.index', ['tab' => 'liquidacao'])
            ->with('success', 'Regras de liquidação atualizadas.');
    }

    public function updateFees(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'merchant_fee_rules' => ['required', 'array'],
        ]);
        $rules = ['pix', 'card', 'boleto', 'withdrawal'];
        foreach ($rules as $key) {
            $request->validate([
                "merchant_fee_rules.$key" => ['nullable', 'array'],
                "merchant_fee_rules.$key.percent" => ['nullable', 'numeric', 'min:0', 'max:100'],
                "merchant_fee_rules.$key.fixed" => ['nullable', 'numeric', 'min:0', 'max:999999'],
            ]);
        }

        $out = [];
        foreach ($rules as $key) {
            $block = $validated['merchant_fee_rules'][$key] ?? [];
            $out[$key] = [
                'percent' => round((float) ($block['percent'] ?? 0), 4),
                'fixed' => round((float) ($block['fixed'] ?? 0), 2),
            ];
        }

        Setting::set('merchant_fee_rules', $out, null);

        PlatformAuditService::log('platform.financial.fees_updated', ['rules' => $out], $request);

        return redirect()->route('plataforma.financeiro.index', ['tab' => 'taxas'])
            ->with('success', 'Taxas da plataforma atualizadas.');
    }

    public function approveWithdrawal(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        if ($withdrawal->status !== 'pending') {
            return redirect()->route('plataforma.saques.index')
                ->with('error', 'Este saque não está pendente.');
        }

        $validated = $request->validate([
            'payout_manual' => ['nullable', 'boolean'],
        ]);
        $manual = (bool) ($validated['payout_manual'] ?? false);

        if ($manual) {
            $withdrawal->update([
                'payout_manual' => true,
                'payout_provider' => 'manual',
            ]);
            MerchantWithdrawalService::markPaid($withdrawal->fresh());

            PlatformAuditService::log('platform.withdrawal.approved', ['withdrawal_id' => $withdrawal->id, 'manual' => true], $request);

            return redirect()->route('plataforma.saques.index')
                ->with('success', 'Saque marcado como pago (aprovado manualmente, sem API).');
        }

        $slug = PlatformPayoutGateway::activeSlug();
        if ($slug === null) {
            return redirect()->route('plataforma.saques.index')
                ->with('error', 'Nenhum gateway de payout PIX está conectado. Use aprovação manual ou configure Integrações > Gateways.');
        }

        $tenantId = (int) $withdrawal->tenant_id;
        $owner = User::query()
            ->where('tenant_id', $tenantId)
            ->where('role', User::ROLE_INFOPRODUTOR)
            ->first();
        if ($owner === null) {
            $owner = User::query()->where('id', $tenantId)->where('role', User::ROLE_INFOPRODUTOR)->first();
        }

        if ($owner === null) {
            return redirect()->route('plataforma.saques.index')
                ->with('error', 'Titular da conta (infoprodutor) não encontrado.');
        }

        $settings = is_array($owner->payout_settings) ? $owner->payout_settings : [];

        if ($slug === 'cajupay') {
            $pixKeyId = isset($settings['cajupay_pix_key_id']) ? trim((string) $settings['cajupay_pix_key_id']) : '';

            if ($pixKeyId === '') {
                return redirect()->route('plataforma.saques.index')
                    ->with('error', 'O infoprodutor precisa cadastrar uma chave PIX para saque em Financeiro (painel do vendedor).');
            }

            $payout = new CajuPayPayoutService;
            $result = $payout->sendWithdrawalToPixKey($withdrawal->fresh(), $pixKeyId);

            if (! ($result['ok'] ?? false)) {
                $prev = is_array($withdrawal->payout_meta) ? $withdrawal->payout_meta : [];
                $withdrawal->update([
                    'payout_provider' => 'cajupay',
                    'payout_meta' => $prev + [
                        'last_error' => $result['error'] ?? 'Erro desconhecido',
                        'last_attempt_at' => now()->toIso8601String(),
                    ],
                ]);

                return redirect()->route('plataforma.saques.index')
                    ->with('error', 'CajuPay: '.($result['error'] ?? 'Falha ao enviar o saque.'));
            }

            $withdrawal->update([
                'payout_manual' => false,
                'payout_provider' => 'cajupay',
                'payout_external_id' => $result['external_id'] ?? null,
                'payout_meta' => array_filter([
                    'api_status' => $result['status'] ?? null,
                    'paid_at' => now()->toIso8601String(),
                ]),
            ]);

            MerchantWithdrawalService::markPaid($withdrawal->fresh());

            PlatformAuditService::log('platform.withdrawal.approved', ['withdrawal_id' => $withdrawal->id, 'cajupay' => true], $request);

            return redirect()->route('plataforma.saques.index')
                ->with('success', 'Saque enviado via CajuPay e marcado como pago.');
        }

        if ($slug === 'spacepag') {
            $payout = new SpacepagPayoutService;
            $result = $payout->sendWithdrawalToPix($withdrawal->fresh(), $owner);

            if (! ($result['ok'] ?? false)) {
                $prev = is_array($withdrawal->payout_meta) ? $withdrawal->payout_meta : [];
                $withdrawal->update([
                    'payout_provider' => 'spacepag',
                    'payout_meta' => $prev + [
                        'last_error' => $result['error'] ?? 'Erro desconhecido',
                        'last_attempt_at' => now()->toIso8601String(),
                    ],
                ]);

                return redirect()->route('plataforma.saques.index')
                    ->with('error', 'Spacepag: '.($result['error'] ?? 'Falha ao enviar o saque.'));
            }

            $withdrawal->update([
                'payout_manual' => false,
                'payout_provider' => 'spacepag',
                'payout_external_id' => $result['transaction_id'] ?? null,
                'payout_meta' => array_filter([
                    'api_status' => 'pending',
                    'requested_at' => now()->toIso8601String(),
                ]),
            ]);

            ReconcileSpacepagWithdrawalJob::dispatch($withdrawal->fresh()->id)
                ->delay(now()->addSeconds(90));

            PlatformAuditService::log('platform.withdrawal.approved', ['withdrawal_id' => $withdrawal->id, 'spacepag' => true, 'pending' => true], $request);

            return redirect()->route('plataforma.saques.index')
                ->with('success', 'Saque enviado à Spacepag. Será marcado como pago após confirmação do PIX (webhook).');
        }

        if ($slug === 'woovi') {
            $pixKey = PayoutUserSettings::pixKey($settings);
            if ($pixKey === '') {
                return redirect()->route('plataforma.saques.index')
                    ->with('error', 'O infoprodutor precisa cadastrar uma chave PIX para saque em Financeiro (painel do vendedor).');
            }

            $payout = new WooviPayoutService;
            $result = $payout->sendWithdrawalToPix($withdrawal->fresh(), $owner);

            if (! ($result['ok'] ?? false)) {
                $prev = is_array($withdrawal->payout_meta) ? $withdrawal->payout_meta : [];
                $withdrawal->update([
                    'payout_provider' => 'woovi',
                    'payout_meta' => $prev + [
                        'last_error' => $result['error'] ?? 'Erro desconhecido',
                        'last_attempt_at' => now()->toIso8601String(),
                    ],
                ]);

                return redirect()->route('plataforma.saques.index')
                    ->with('error', 'Woovi: '.($result['error'] ?? 'Falha ao enviar o saque.'));
            }

            $withdrawal->update([
                'payout_manual' => false,
                'payout_provider' => 'woovi',
                'payout_external_id' => $result['transaction_id'] ?? null,
                'payout_meta' => array_filter([
                    'api_status' => 'pending',
                    'requested_at' => now()->toIso8601String(),
                ]),
            ]);

            ReconcileWooviWithdrawalJob::dispatch($withdrawal->fresh()->id)
                ->delay(now()->addSeconds(90));

            PlatformAuditService::log('platform.withdrawal.approved', ['withdrawal_id' => $withdrawal->id, 'woovi' => true, 'pending' => true], $request);

            return redirect()->route('plataforma.saques.index')
                ->with('success', 'Saque enviado à Woovi. Será marcado como pago após confirmação na API.');
        }

        return redirect()->route('plataforma.saques.index')
            ->with('error', 'Gateway de payout não suportado.');
    }

    public function rejectWithdrawal(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($withdrawal->status !== 'pending') {
            return redirect()->route('plataforma.saques.index')
                ->with('error', 'Este saque não está pendente.');
        }

        MerchantWithdrawalService::reject($withdrawal, $validated['admin_note'] ?? null);

        PlatformAuditService::log('platform.withdrawal.rejected', ['withdrawal_id' => $withdrawal->id], $request);

        return redirect()->route('plataforma.saques.index')
            ->with('success', 'Saque rejeitado e saldo devolvido ao infoprodutor.');
    }
}
