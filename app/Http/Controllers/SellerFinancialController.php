<?php

namespace App\Http\Controllers;

use App\Http\Controllers\InfoprodutorRegistrationController;
use App\Models\GatewayCredential;
use App\Models\User;
use App\Models\TenantWallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\CajuPay\CajuPayPayoutService;
use App\Services\EffectiveMerchantFees;
use App\Services\EffectiveSettlementRules;
use App\Services\MerchantWithdrawalService;
use App\Services\Payout\PayoutUserSettings;
use App\Services\Payout\PlatformPayoutGateway;
use App\Services\WithdrawalAutoPayoutService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SellerFinancialController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tenantId = (int) ($user->tenant_id ?? $user->id);

        $wallet = null;
        if (Schema::hasTable('tenant_wallets')) {
            $wallet = TenantWallet::query()->firstOrCreate(
                ['tenant_id' => $tenantId],
                [
                    'available_balance' => 0,
                    'pending_balance' => 0,
                    'currency' => 'BRL',
                    'available_pix' => 0,
                    'available_card' => 0,
                    'available_boleto' => 0,
                    'pending_pix' => 0,
                    'pending_card' => 0,
                    'pending_boleto' => 0,
                ]
            );
        }

        $withdrawals = [];
        if (Schema::hasTable('withdrawals')) {
            $withdrawals = Withdrawal::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('id')
                ->limit(80)
                ->get()
                ->map(fn ($w) => [
                    'id' => $w->id,
                    'amount' => (float) $w->amount,
                    'fee_amount' => (float) ($w->fee_amount ?? 0),
                    'net_amount' => (float) ($w->net_amount ?? 0),
                    'bucket' => $w->bucket ?? 'pix',
                    'status' => $w->status,
                    'notes' => $w->notes,
                    'created_at' => $w->created_at?->toIso8601String(),
                ])
                ->all();
        }

        $feesPreview = EffectiveMerchantFees::forTenant($tenantId);

        $reservePendingTotal = 0.0;
        if (Schema::hasTable('wallet_transactions')) {
            $reservePendingTotal = round((float) WalletTransaction::query()
                ->where('tenant_id', $tenantId)
                ->where('type', WalletTransaction::TYPE_CREDIT_SALE_PENDING)
                ->get()
                ->filter(function (WalletTransaction $t) {
                    $m = is_array($t->meta) ? $t->meta : [];

                    return ($m['portion'] ?? '') === 'reserve' && empty($m['released_at'] ?? null);
                })
                ->sum(fn (WalletTransaction $t) => (float) $t->amount_net), 2);
        }

        $payoutGateway = PlatformPayoutGateway::activeSlug();
        $payoutPixSetup = match ($payoutGateway) {
            'cajupay' => 'label_and_key',
            'spacepag' => 'key_and_receiver',
            'woovi' => 'pix_key_only',
            default => null,
        };

        $walletPayload = null;
        if ($wallet !== null) {
            $pp = (float) ($wallet->pending_pix ?? 0);
            $pc = (float) ($wallet->pending_card ?? 0);
            $pb = (float) ($wallet->pending_boleto ?? 0);
            $walletPayload = [
                'available_pix' => (float) ($wallet->available_pix ?? 0),
                'available_card' => (float) ($wallet->available_card ?? 0),
                'available_boleto' => (float) ($wallet->available_boleto ?? 0),
                'pending_pix' => $pp,
                'pending_card' => $pc,
                'pending_boleto' => $pb,
                'pending_total' => round($pp + $pc + $pb, 2),
                'reserve_pending_total' => $reservePendingTotal,
                'available_total' => round(
                    (float) ($wallet->available_pix ?? 0)
                    + (float) ($wallet->available_card ?? 0)
                    + (float) ($wallet->available_boleto ?? 0),
                    2
                ),
            ];
        }

        $subject = $user->kycSubjectUser();
        $kycFinanceLocked = Schema::hasColumn('users', 'kyc_status')
            && ! $subject->hasApprovedKyc();

        return Inertia::render('Financeiro/Index', [
            'wallet' => $walletPayload,
            'withdrawals' => $withdrawals,
            'seller_profile' => [
                'name' => (string) ($user->name ?? ''),
                'email' => (string) ($user->email ?? ''),
                'document' => $user->document !== null && $user->document !== '' ? (string) $user->document : null,
            ],
            'kyc_status' => Schema::hasColumn('users', 'kyc_status') ? ($subject->kyc_status ?? User::KYC_NOT_SUBMITTED) : null,
            'kyc_person_type' => Schema::hasColumn('users', 'person_type') ? ($subject->person_type ?? 'pf') : 'pf',
            'kyc_rejection_reason' => Schema::hasColumn('users', 'kyc_rejection_reason')
                ? ($subject->kyc_rejection_reason ?? null)
                : null,
            'kyc_finance_locked' => $kycFinanceLocked,
            'registration_snapshot' => self::buildRegistrationSnapshot($subject),
            'payout_settings' => is_array($user->payout_settings) ? $user->payout_settings : [],
            /** @var 'label_and_key'|'key_and_receiver'|null Fluxo de cadastro PIX sem expor adquirente ao vendedor */
            'payout_pix_setup' => $payoutPixSetup,
            'fee_preview' => $feesPreview,
            'settlement_preview' => [
                'pix' => EffectiveSettlementRules::forTenantMethod($tenantId, 'pix'),
                'card' => EffectiveSettlementRules::forTenantMethod($tenantId, 'card'),
                'boleto' => EffectiveSettlementRules::forTenantMethod($tenantId, 'boleto'),
            ],
        ]);
    }

    /**
     * Dados informados no cadastro (somente leitura na aba Financeiro » Seus dados).
     *
     * @return array<string, mixed>
     */
    private static function buildRegistrationSnapshot(User $subject): array
    {
        $personType = Schema::hasColumn('users', 'person_type') ? (string) ($subject->person_type ?? 'pf') : 'pf';
        $docRaw = (string) ($subject->document ?? '');
        $docDisplay = $personType === 'pj' ? self::maskCnpjForDisplay($docRaw) : self::maskCpfForDisplay($docRaw);

        $birth = null;
        if (Schema::hasColumn('users', 'birth_date') && $subject->birth_date !== null) {
            try {
                $birth = Carbon::parse($subject->birth_date)->format('d/m/Y');
            } catch (\Throwable) {
                $birth = (string) $subject->birth_date;
            }
        }

        $repCpfDisplay = null;
        if ($personType === 'pj' && Schema::hasColumn('users', 'legal_representative_cpf')) {
            $repCpfDisplay = self::maskCpfForDisplay((string) ($subject->legal_representative_cpf ?? ''));
        }

        $revenueLabel = null;
        if (Schema::hasColumn('users', 'monthly_revenue_range') && $subject->monthly_revenue_range) {
            foreach (InfoprodutorRegistrationController::revenueRangeOptions() as $opt) {
                if (($opt['value'] ?? '') === $subject->monthly_revenue_range) {
                    $revenueLabel = $opt['label'] ?? null;
                    break;
                }
            }
            $revenueLabel ??= (string) $subject->monthly_revenue_range;
        }

        $zip = (string) ($subject->address_zip ?? '');
        $zipDisplay = strlen($zip) === 8 ? substr($zip, 0, 5).'-'.substr($zip, 5) : ($zip !== '' ? $zip : null);

        return [
            'person_type' => $personType,
            'person_type_label' => $personType === 'pj' ? 'Pessoa jurídica' : 'Pessoa física',
            'name' => (string) ($subject->name ?? ''),
            'email' => (string) ($subject->email ?? ''),
            'birth_date' => $birth,
            'document' => $docDisplay,
            'company_name' => $personType === 'pj' ? (trim((string) ($subject->company_name ?? '')) ?: null) : null,
            'legal_representative_cpf' => $repCpfDisplay,
            'address_zip' => $zipDisplay,
            'address_street' => (string) ($subject->address_street ?? ''),
            'address_number' => (string) ($subject->address_number ?? ''),
            'address_complement' => trim((string) ($subject->address_complement ?? '')) ?: null,
            'address_neighborhood' => (string) ($subject->address_neighborhood ?? ''),
            'address_city' => (string) ($subject->address_city ?? ''),
            'address_state' => strtoupper((string) ($subject->address_state ?? '')),
            'monthly_revenue_label' => $revenueLabel,
        ];
    }

    private static function maskCpfForDisplay(string $digits): string
    {
        $d = preg_replace('/\D/', '', $digits) ?? '';
        if (strlen($d) !== 11) {
            return $digits !== '' ? $digits : '—';
        }

        return sprintf('%s.%s.%s-%s', substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 3), substr($d, 9, 2));
    }

    private static function maskCnpjForDisplay(string $digits): string
    {
        $d = preg_replace('/\D/', '', $digits) ?? '';
        if (strlen($d) !== 14) {
            return $digits !== '' ? $digits : '—';
        }

        return sprintf('%s.%s.%s/%s-%s', substr($d, 0, 2), substr($d, 2, 3), substr($d, 5, 3), substr($d, 8, 4), substr($d, 12, 2));
    }

    public function storePayoutPixKey(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isInfoprodutor()) {
            abort(403);
        }

        if (Schema::hasColumn('users', 'kyc_status') && ! $user->kycSubjectUser()->hasApprovedKyc()) {
            return redirect()->route('financeiro.seller.index')
                ->with('error', 'Conclua a verificação de identidade (KYC) para salvar dados de recebimento.');
        }

        $slug = PlatformPayoutGateway::activeSlug();
        if ($slug === null) {
            return redirect()->route('financeiro.seller.index')
                ->with('error', 'A plataforma ainda não configurou o recebimento automático de saques por PIX.');
        }

        if ($slug === 'cajupay') {
            $validated = $request->validate([
                'label' => ['required', 'string', 'max:120'],
                'pix_key_type' => ['required', 'string', 'in:cpf,cnpj,email,phone,evp'],
                'pix_key' => ['required', 'string', 'max:120'],
            ]);

            $cred = GatewayCredential::resolveForPayment(null, 'cajupay');
            if ($cred === null || ! $cred->is_connected) {
                return redirect()->route('financeiro.seller.index')
                    ->with('error', 'A plataforma ainda não configurou o recebimento automático de saques por PIX.');
            }

            $credentials = $cred->getDecryptedCredentials();
            $res = CajuPayPayoutService::createPixKey($credentials, [
                'label' => $validated['label'],
                'pix_key_type' => $validated['pix_key_type'],
                'pix_key' => trim($validated['pix_key']),
                'is_default' => true,
            ]);

            if (! ($res['ok'] ?? false)) {
                return redirect()->route('financeiro.seller.index')
                    ->with('error', $res['error'] ?? 'Não foi possível cadastrar a chave PIX.');
            }

            $id = $res['id'] ?? null;
            if (! is_string($id) || $id === '') {
                return redirect()->route('financeiro.seller.index')
                    ->with('error', 'Não foi possível confirmar o cadastro da chave. Tente novamente ou contate o suporte.');
            }

            $settings = is_array($user->payout_settings) ? $user->payout_settings : [];
            $settings['cajupay_pix_key_id'] = $id;
            $settings['cajupay_pix_label'] = $validated['label'];
            $settings['payout_pix_label'] = $validated['label'];
            $user->payout_settings = $settings;
            $user->save();

            return redirect()->route('financeiro.seller.index')->with('success', 'Dados para recebimento de saques atualizados.');
        }

        if ($slug === 'spacepag') {
            $validated = $request->validate([
                'pix_key' => ['required', 'string', 'max:120'],
                'pix_key_type' => ['required', 'string', 'in:cpf,cnpj,email,phone,evp'],
                'receiver_name' => ['required', 'string', 'max:120'],
                'receiver_document' => ['required', 'string', 'max:20'],
                'receiver_email' => ['required', 'email', 'max:255'],
            ]);

            $settings = is_array($user->payout_settings) ? $user->payout_settings : [];
            $pk = trim($validated['pix_key']);
            $pkt = $validated['pix_key_type'];
            $settings['payout_pix_key'] = $pk;
            $settings['payout_pix_key_type'] = $pkt;
            $settings['spacepag_pix_key'] = $pk;
            $settings['spacepag_pix_key_type'] = $pkt;
            $settings['receiver_name'] = trim($validated['receiver_name']);
            $settings['receiver_document'] = trim($validated['receiver_document']);
            $settings['receiver_email'] = trim($validated['receiver_email']);
            $user->payout_settings = $settings;
            $user->save();

            return redirect()->route('financeiro.seller.index')->with('success', 'Dados para recebimento de saques salvos.');
        }

        if ($slug === 'woovi') {
            $validated = $request->validate([
                'pix_key' => ['required', 'string', 'max:120'],
                'pix_key_type' => ['required', 'string', 'in:cpf,cnpj,email,phone,evp'],
            ]);

            $settings = is_array($user->payout_settings) ? $user->payout_settings : [];
            $pk = trim($validated['pix_key']);
            $pkt = $validated['pix_key_type'];
            $settings['payout_pix_key'] = $pk;
            $settings['payout_pix_key_type'] = $pkt;
            $settings['woovi_pix_key'] = $pk;
            $settings['woovi_pix_key_type'] = $pkt;
            $user->payout_settings = $settings;
            $user->save();

            return redirect()->route('financeiro.seller.index')->with('success', 'Chave PIX para saques salva.');
        }

        return redirect()->route('financeiro.seller.index')
            ->with('error', 'Gateway de payout não suportado.');
    }

    public function storeWithdrawal(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'bucket' => ['required', 'string', 'in:pix,card,boleto'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        if (Schema::hasColumn('users', 'kyc_status') && ! $user->kycSubjectUser()->hasApprovedKyc()) {
            return redirect()->route('financeiro.seller.index')
                ->with('error', 'Conclua a verificação de identidade (KYC) para solicitar saques.');
        }

        $slug = PlatformPayoutGateway::activeSlug();
        if ($slug === 'cajupay') {
            $settings = is_array($user->payout_settings) ? $user->payout_settings : [];
            $pixId = isset($settings['cajupay_pix_key_id']) ? trim((string) $settings['cajupay_pix_key_id']) : '';
            if ($pixId === '') {
                throw ValidationException::withMessages([
                    'amount' => 'Cadastre os dados de PIX para recebimento (seção acima) antes de solicitar o saque.',
                ]);
            }
        }
        if ($slug === 'spacepag') {
            $settings = is_array($user->payout_settings) ? $user->payout_settings : [];
            $ok = PayoutUserSettings::pixKey($settings) !== ''
                && PayoutUserSettings::pixKeyType($settings) !== ''
                && trim((string) ($settings['receiver_name'] ?? '')) !== ''
                && trim((string) ($settings['receiver_document'] ?? '')) !== ''
                && trim((string) ($settings['receiver_email'] ?? '')) !== '';
            if (! $ok) {
                throw ValidationException::withMessages([
                    'amount' => 'Preencha os dados de PIX e recebedor (seção acima) antes de solicitar o saque.',
                ]);
            }
        }
        if ($slug === 'woovi') {
            $settings = is_array($user->payout_settings) ? $user->payout_settings : [];
            if (PayoutUserSettings::pixKey($settings) === '' || PayoutUserSettings::pixKeyType($settings) === '') {
                throw ValidationException::withMessages([
                    'amount' => 'Cadastre a chave PIX de destino (seção acima) antes de solicitar o saque.',
                ]);
            }
        }

        $withdrawal = MerchantWithdrawalService::requestWithdrawal(
            $request->user(),
            (float) $validated['amount'],
            $validated['bucket'],
            $validated['notes'] ?? null
        );

        if (PlatformPayoutGateway::isEnabled()) {
            $auto = app(WithdrawalAutoPayoutService::class)->attemptAutoPayout($withdrawal);

            if (($auto['ok'] ?? false) === true) {
                if (($auto['pending'] ?? false) === true) {
                    return redirect()->route('financeiro.seller.index')
                        ->with('success', 'Seu saque está sendo processado.');
                }

                return redirect()->route('financeiro.seller.index')
                    ->with('success', 'Saque enviado via PIX e marcado como concluído.');
            }

            if (($auto['skipped'] ?? false) === true) {
                return redirect()->route('financeiro.seller.index')
                    ->with('success', 'Solicitação de saque registrada. Complete o cadastro de dados de recebimento PIX para envio automático.');
            }

            return redirect()->route('financeiro.seller.index')
                ->with('error', 'Saque registrado, mas o envio automático falhou: '.($auto['error'] ?? 'tente novamente ou contate o suporte.'));
        }

        return redirect()->route('financeiro.seller.index')
            ->with('success', 'Solicitação de saque registrada. Aguarde a análise da plataforma.');
    }
}
