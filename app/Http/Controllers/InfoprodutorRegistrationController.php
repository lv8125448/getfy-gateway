<?php

namespace App\Http\Controllers;

use App\Models\ProductCoproducer;
use App\Models\TenantWallet;
use App\Models\User;
use App\Services\PlatformEmailNotifications;
use App\Support\BrazilianDocuments;
use App\Support\DockerSetupState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InfoprodutorRegistrationController extends Controller
{
    public function __construct(
        protected PlatformEmailNotifications $platformEmailNotifications
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if (DockerSetupState::isDocker() && ! DockerSetupState::isSetupDone()) {
            return redirect('/docker-setup');
        }

        if (User::count() === 0) {
            return redirect()->route('criar-admin');
        }

        return Inertia::render('Auth/RegisterWizard', [
            'revenue_ranges' => self::revenueRangeOptions(),
            'coproducer_invite' => $request->query('coproducer_invite'),
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function revenueRangeOptions(): array
    {
        return [
            ['value' => 'up_to_10k', 'label' => 'Até R$ 10 mil'],
            ['value' => '10k_50k', 'label' => 'R$ 10 mil a R$ 50 mil'],
            ['value' => '50k_100k', 'label' => 'R$ 50 mil a R$ 100 mil'],
            ['value' => '100k_500k', 'label' => 'R$ 100 mil a R$ 500 mil'],
            ['value' => 'over_500k', 'label' => 'Acima de R$ 500 mil'],
        ];
    }

    public function validateEmail(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $exists = User::query()->where('email', $validated['email'])->exists();

        return response()->json(['available' => ! $exists]);
    }

    public function validateDocument(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'person_type' => ['required', 'string', Rule::in(['pf', 'pj'])],
            'document' => ['required', 'string', 'max:20'],
            'legal_representative_cpf' => ['nullable', 'string', 'max:20'],
        ]);

        $docDigits = BrazilianDocuments::digits($validated['document']);

        if ($validated['person_type'] === 'pf') {
            if (! BrazilianDocuments::isValidCpf($docDigits)) {
                return response()->json([
                    'available' => false,
                    'message' => 'CPF inválido.',
                ], 422);
            }
            if (User::query()->where('document', $docDigits)->exists()) {
                return response()->json([
                    'available' => false,
                    'field' => 'document',
                    'message' => 'Este CPF já está cadastrado.',
                ]);
            }

            return response()->json(['available' => true]);
        }

        if (! BrazilianDocuments::isValidCnpj($docDigits)) {
            return response()->json([
                'available' => false,
                'message' => 'CNPJ inválido.',
            ], 422);
        }

        if (User::query()->where('document', $docDigits)->exists()) {
            return response()->json([
                'available' => false,
                'field' => 'document',
                'message' => 'Este CNPJ já está cadastrado.',
            ]);
        }

        $rep = BrazilianDocuments::digits((string) ($validated['legal_representative_cpf'] ?? ''));
        if ($rep === '' || ! BrazilianDocuments::isValidCpf($rep)) {
            return response()->json([
                'available' => false,
                'message' => 'CPF do representante legal inválido.',
            ], 422);
        }

        if (User::query()->where('legal_representative_cpf', $rep)->exists()) {
            return response()->json([
                'available' => false,
                'field' => 'legal_representative_cpf',
                'message' => 'Este CPF já está vinculado a outra conta.',
            ]);
        }

        if (User::query()->where('document', $rep)->exists()) {
            return response()->json([
                'available' => false,
                'field' => 'legal_representative_cpf',
                'message' => 'Este CPF já está cadastrado como titular de outra conta.',
            ]);
        }

        return response()->json(['available' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (DockerSetupState::isDocker() && ! DockerSetupState::isSetupDone()) {
            return redirect('/docker-setup');
        }

        if (User::count() === 0) {
            abort(403, 'Cadastro indisponível.');
        }

        $rules = [
            'person_type' => ['required', 'string', Rule::in(['pf', 'pj'])],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'birth_date' => ['required', 'date', 'before:'.now()->subYears(18)->format('Y-m-d')],
            'document' => ['required', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'legal_representative_cpf' => ['nullable', 'string', 'max:20'],
            'address_zip' => ['required', 'string', 'regex:/^\d{8}$/'],
            'address_street' => ['required', 'string', 'max:255'],
            'address_number' => ['required', 'string', 'max:32'],
            'address_complement' => ['nullable', 'string', 'max:120'],
            'address_neighborhood' => ['required', 'string', 'max:120'],
            'address_city' => ['required', 'string', 'max:120'],
            'address_state' => ['required', 'string', 'size:2'],
            'monthly_revenue_range' => ['required', 'string', Rule::in(User::MONTHLY_REVENUE_RANGES)],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'coproducer_invite' => ['nullable', 'string', 'max:64'],
        ];

        $validated = $request->validate($rules, [
            'email.unique' => 'Este e-mail já está em uso.',
            'birth_date.before' => 'É necessário ter pelo menos 18 anos.',
        ]);

        $docDigits = BrazilianDocuments::digits($validated['document']);
        if ($validated['person_type'] === 'pf') {
            if (! BrazilianDocuments::isValidCpf($docDigits)) {
                return back()->withErrors(['document' => 'CPF inválido.'])->withInput();
            }
        } else {
            if (! BrazilianDocuments::isValidCnpj($docDigits)) {
                return back()->withErrors(['document' => 'CNPJ inválido.'])->withInput();
            }
            if (empty(trim((string) ($validated['company_name'] ?? '')))) {
                return back()->withErrors(['company_name' => 'Informe a razão social da empresa.'])->withInput();
            }
            $rep = BrazilianDocuments::digits((string) ($validated['legal_representative_cpf'] ?? ''));
            if ($rep === '' || ! BrazilianDocuments::isValidCpf($rep)) {
                return back()->withErrors(['legal_representative_cpf' => 'CPF do representante legal inválido.'])->withInput();
            }
        }

        if (User::query()->where('document', $docDigits)->exists()) {
            return back()->withErrors([
                'document' => $validated['person_type'] === 'pf'
                    ? 'Este CPF já está cadastrado.'
                    : 'Este CNPJ já está cadastrado.',
            ])->withInput();
        }

        if ($validated['person_type'] === 'pj') {
            $repDigits = BrazilianDocuments::digits((string) $validated['legal_representative_cpf']);
            if (User::query()->where('legal_representative_cpf', $repDigits)->exists()) {
                return back()->withErrors(['legal_representative_cpf' => 'Este CPF já está vinculado a outra conta.'])->withInput();
            }
            if (User::query()->where('document', $repDigits)->exists()) {
                return back()->withErrors(['legal_representative_cpf' => 'Este CPF já está cadastrado como titular de outra conta.'])->withInput();
            }
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_INFOPRODUTOR,
            'person_type' => $validated['person_type'],
            'document' => $docDigits,
            'birth_date' => $validated['birth_date'],
            'company_name' => $validated['person_type'] === 'pj' ? trim((string) $validated['company_name']) : null,
            'legal_representative_cpf' => $validated['person_type'] === 'pj'
                ? BrazilianDocuments::digits((string) $validated['legal_representative_cpf'])
                : null,
            'address_zip' => $validated['address_zip'],
            'address_street' => $validated['address_street'],
            'address_number' => $validated['address_number'],
            'address_complement' => $validated['address_complement'] ?? null,
            'address_neighborhood' => $validated['address_neighborhood'],
            'address_city' => $validated['address_city'],
            'address_state' => strtoupper($validated['address_state']),
            'monthly_revenue_range' => $validated['monthly_revenue_range'],
            'kyc_status' => User::KYC_NOT_SUBMITTED,
            'account_status' => 'approved',
        ]);

        $user->update(['tenant_id' => $user->id]);

        if (Schema::hasTable('tenant_wallets')) {
            TenantWallet::query()->firstOrCreate(
                ['tenant_id' => $user->tenant_id],
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

        $this->platformEmailNotifications->welcomeInfoprodutor($user->fresh());

        Auth::login($user);
        $request->session()->regenerate();

        $inviteAccepted = false;
        if (! empty($validated['coproducer_invite'])) {
            $inviteAccepted = ProductCoproducer::tryActivateAfterRegistration($user->fresh(), $validated['coproducer_invite']);
        }

        $msg = $inviteAccepted
            ? 'Conta criada e co-produção ativada. Complete a verificação de identidade (KYC) para liberar o Financeiro.'
            : 'Conta criada. Complete a verificação de identidade (KYC) para liberar o Financeiro.';

        return redirect()->intended('/dashboard')->with('success', $msg);
    }
}
