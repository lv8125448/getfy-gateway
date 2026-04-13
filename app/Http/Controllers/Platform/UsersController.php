<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\ProvidesPlatformGatewayProps;
use App\Http\Controllers\Controller;
use App\Support\PlatformConfigContext;
use App\Gateways\GatewayRegistry;
use App\Models\TenantWallet;
use App\Models\User;
use App\Services\MerchantWalletAdminBlockService;
use App\Services\PlatformAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller
{
    use ProvidesPlatformGatewayProps;

    public function index(): Response
    {
        $users = User::query()
            ->where('role', User::ROLE_INFOPRODUTOR)
            ->orderBy('name')
            ->get();

        $tenantIds = $users->pluck('tenant_id')->filter()->unique()->values();
        $wallets = collect();
        if (Schema::hasTable('tenant_wallets')) {
            $wallets = TenantWallet::query()
                ->whereIn('tenant_id', $tenantIds)
                ->get()
                ->keyBy('tenant_id');
        }

        $rows = $users->map(function (User $u) use ($wallets) {
            $tid = $u->tenant_id ?? $u->id;
            $w = $wallets->get($tid);
            $tidInt = (int) $tid;
            $medTotal = $tidInt > 0 ? MerchantWalletAdminBlockService::totalMedHoldAmountForTenant($tidInt) : 0.0;

            $walletAdmin = null;
            if ($w && Schema::hasColumn('tenant_wallets', 'admin_withdrawal_blocked')) {
                $walletAdmin = [
                    'admin_withdrawal_blocked' => (bool) $w->admin_withdrawal_blocked,
                    'admin_blocked_amount' => $w->admin_blocked_amount !== null ? (float) $w->admin_blocked_amount : null,
                    'admin_block_until' => $w->admin_block_until?->toIso8601String(),
                    'admin_block_note' => $w->admin_block_note,
                ];
            }

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar_url' => $u->avatar ? app(\App\Services\StorageService::class)->url($u->avatar) : null,
                'tenant_id' => $u->tenant_id,
                'person_type' => $u->person_type,
                'document' => $u->document,
                'account_status' => $u->account_status ?? 'approved',
                'merchant_fees' => $u->merchant_fees ?? [],
                'merchant_settlement_overrides' => $u->merchant_settlement_overrides ?? [],
                'merchant_gateway_order' => $u->merchant_gateway_order ?? [],
                'saldo_disponivel' => $w ? (float) $w->available_balance : 0.0,
                'saldo_pix' => $w ? (float) $w->pending_balance : 0.0,
                'med_total' => $medTotal,
                'wallet_admin' => $walletAdmin,
                'created_at' => $u->created_at?->toIso8601String(),
            ];
        });

        $settingsTenantId = PlatformConfigContext::settingsTenantId();

        return Inertia::render('Platform/Users/Index', [
            'users' => $rows,
            'gateways' => $this->buildGatewaysListForMerchantPicker(),
            'platform_gateway_order' => $this->buildGatewayOrderForSettings($settingsTenantId),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Platform/Users/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.unique' => 'Este e-mail já está em uso.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ]);

        $attrs = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_INFOPRODUTOR,
            'account_status' => 'approved',
        ];
        if (Schema::hasColumn('users', 'kyc_status')) {
            $attrs['kyc_status'] = User::KYC_APPROVED;
        }
        $user = User::create($attrs);

        $user->update(['tenant_id' => $user->id]);

        if (Schema::hasTable('tenant_wallets')) {
            TenantWallet::query()->firstOrCreate(
                ['tenant_id' => $user->tenant_id],
                ['available_balance' => 0, 'pending_balance' => 0, 'currency' => 'BRL']
            );
        }

        PlatformAuditService::log('platform.merchant.created', ['user_id' => $user->id], $request);

        return redirect()->route('plataforma.usuarios.index')->with('success', 'Infoprodutor cadastrado com sucesso.');
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('manageMerchantForPlatform', $user);

        $id = $user->id;
        $user->delete();

        PlatformAuditService::log('platform.merchant.deleted', ['user_id' => $id], request());

        return redirect()->route('plataforma.usuarios.index')->with('success', 'Usuário excluído.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageMerchantForPlatform', $user);

        $prevAccountStatus = $user->account_status;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'account_status' => ['nullable', 'string', 'in:approved,pending,rejected,suspended,blocked'],
            'admin_withdrawal_blocked' => ['nullable', 'boolean'],
            'admin_blocked_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'admin_block_until' => ['nullable', 'date'],
            'admin_block_note' => ['nullable', 'string', 'max:500'],
        ], [
            'email.unique' => 'Este e-mail já está em uso.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if ($request->has('account_status') && isset($validated['account_status'])) {
            $user->account_status = $validated['account_status'];
        }

        if ($request->has('merchant_fees')) {
            $user->merchant_fees = $this->normalizeMerchantFeesOverrides(
                is_array($request->input('merchant_fees')) ? $request->input('merchant_fees') : null
            );
        }

        if ($request->has('merchant_settlement_overrides')) {
            $user->merchant_settlement_overrides = $this->normalizeMerchantSettlementOverrides(
                is_array($request->input('merchant_settlement_overrides')) ? $request->input('merchant_settlement_overrides') : null
            );
        }

        if ($request->has('merchant_gateway_order')) {
            $user->merchant_gateway_order = $this->normalizeMerchantGatewayOrder(
                is_array($request->input('merchant_gateway_order')) ? $request->input('merchant_gateway_order') : null
            );
        }

        $user->save();

        if (Schema::hasTable('tenant_wallets') && Schema::hasColumn('tenant_wallets', 'admin_withdrawal_blocked')) {
            $tenantId = (int) ($user->tenant_id ?? $user->id);
            if ($tenantId > 0) {
                $wallet = TenantWallet::query()->firstOrCreate(
                    ['tenant_id' => $tenantId],
                    ['available_balance' => 0, 'pending_balance' => 0, 'currency' => 'BRL']
                );
                $wallet->admin_withdrawal_blocked = $request->boolean('admin_withdrawal_blocked');
                $amt = $request->input('admin_blocked_amount');
                $wallet->admin_blocked_amount = ($amt === null || $amt === '') ? null : round((float) $amt, 2);
                $until = $request->input('admin_block_until');
                $wallet->admin_block_until = ($until === null || $until === '') ? null : $until;
                $wallet->admin_block_note = $request->input('admin_block_note') ?: null;
                if ($wallet->isDirty(['admin_withdrawal_blocked', 'admin_blocked_amount', 'admin_block_until', 'admin_block_note'])) {
                    $wallet->save();
                    PlatformAuditService::log('platform.merchant.wallet_admin_block', [
                        'merchant_user_id' => $user->id,
                        'tenant_id' => $tenantId,
                        'admin_withdrawal_blocked' => $wallet->admin_withdrawal_blocked,
                        'admin_blocked_amount' => $wallet->admin_blocked_amount,
                        'admin_block_until' => $wallet->admin_block_until?->toIso8601String(),
                    ], $request);
                }
            }
        }

        if (($prevAccountStatus ?? null) !== ($user->account_status ?? null)) {
            PlatformAuditService::log('platform.merchant.account_status', [
                'user_id' => $user->id,
                'from' => $prevAccountStatus,
                'to' => $user->account_status,
            ], $request);
        }

        PlatformAuditService::log('platform.merchant.updated', ['user_id' => $user->id], $request);

        return redirect()->route('plataforma.usuarios.index')->with('success', 'Usuário atualizado.');
    }

    /**
     * @param  array<string, mixed>|null  $raw
     */
    private function normalizeMerchantFeesOverrides(?array $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        $out = [];
        foreach (['pix', 'card', 'boleto', 'withdrawal'] as $key) {
            $block = $raw[$key] ?? null;
            if (! is_array($block)) {
                continue;
            }
            $row = [];
            if (array_key_exists('percent', $block) && $block['percent'] !== '' && $block['percent'] !== null) {
                $p = (float) $block['percent'];
                if ($p < 0 || $p > 100) {
                    throw ValidationException::withMessages([
                        "merchant_fees.$key.percent" => 'O percentual deve estar entre 0 e 100.',
                    ]);
                }
                $row['percent'] = round($p, 4);
            }
            if (array_key_exists('fixed', $block) && $block['fixed'] !== '' && $block['fixed'] !== null) {
                $f = (float) $block['fixed'];
                if ($f < 0 || $f > 999999) {
                    throw ValidationException::withMessages([
                        "merchant_fees.$key.fixed" => 'Valor fixo inválido.',
                    ]);
                }
                $row['fixed'] = round($f, 2);
            }
            if ($row !== []) {
                $out[$key] = $row;
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param  array<string, mixed>|null  $raw
     */
    private function normalizeMerchantSettlementOverrides(?array $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        $out = [];
        foreach (['pix', 'card', 'boleto'] as $key) {
            $block = $raw[$key] ?? null;
            if (! is_array($block)) {
                continue;
            }
            $row = [];
            if (array_key_exists('days_to_available', $block) && $block['days_to_available'] !== '' && $block['days_to_available'] !== null) {
                $row['days_to_available'] = max(0, min(365, (int) $block['days_to_available']));
            }
            if (array_key_exists('reserve_percent', $block) && $block['reserve_percent'] !== '' && $block['reserve_percent'] !== null) {
                $row['reserve_percent'] = round(min(100, max(0, (float) $block['reserve_percent'])), 2);
            }
            if (array_key_exists('reserve_hold_days', $block) && $block['reserve_hold_days'] !== '' && $block['reserve_hold_days'] !== null) {
                $row['reserve_hold_days'] = max(0, min(365, (int) $block['reserve_hold_days']));
            }
            if ($row !== []) {
                $out[$key] = $row;
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param  array<string, mixed>|null  $raw
     */
    private function normalizeMerchantGatewayOrder(?array $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        $out = [];
        foreach (['pix', 'card', 'boleto', 'pix_auto'] as $method) {
            $list = $raw[$method] ?? null;
            if (! is_array($list)) {
                continue;
            }
            $slugs = [];
            foreach ($list as $s) {
                if (is_string($s) && preg_match('/^[a-z0-9_-]+$/', $s)) {
                    $slugs[] = $s;
                }
            }
            $slugs = GatewayRegistry::filterSlugsToAllowedAcquirers($slugs);
            if ($slugs !== []) {
                $out[$method] = $slugs;
            }
        }

        return $out === [] ? null : $out;
    }
}
