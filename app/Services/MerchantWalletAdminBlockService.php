<?php

namespace App\Services;

use App\Models\TenantWallet;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class MerchantWalletAdminBlockService
{
    /**
     * Após admin_block_until, remove bloqueios automáticos configurados pela plataforma.
     */
    public static function expireAdminBlockIfNeeded(?TenantWallet $wallet): void
    {
        if ($wallet === null || ! Schema::hasColumn('tenant_wallets', 'admin_block_until')) {
            return;
        }

        $until = $wallet->admin_block_until;
        if ($until === null) {
            return;
        }

        $end = $until instanceof Carbon ? $until : Carbon::parse((string) $until);
        if (now()->greaterThan($end)) {
            $wallet->admin_withdrawal_blocked = false;
            $wallet->admin_blocked_amount = null;
            $wallet->admin_block_until = null;
            $wallet->save();
        }
    }

    /**
     * Saldo efetivo para saque no bucket, após bloqueio administrativo (não altera MED: já refletido em available_*).
     */
    public static function effectiveAvailableForWithdrawal(TenantWallet $wallet, string $bucket): float
    {
        self::expireAdminBlockIfNeeded($wallet);
        $wallet->refresh();

        $bucket = in_array($bucket, ['pix', 'card', 'boleto'], true) ? $bucket : 'pix';
        $col = 'available_'.$bucket;
        if (! Schema::hasColumn('tenant_wallets', $col)) {
            return 0.0;
        }

        $raw = (float) ($wallet->{$col} ?? 0);

        if (Schema::hasColumn('tenant_wallets', 'admin_withdrawal_blocked') && filter_var($wallet->admin_withdrawal_blocked ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return 0.0;
        }

        if (! Schema::hasColumn('tenant_wallets', 'admin_blocked_amount')) {
            return $raw;
        }

        $blockAmt = (float) ($wallet->admin_blocked_amount ?? 0);
        if ($blockAmt <= 0) {
            return round($raw, 2);
        }

        return round(max(0, $raw - min($blockAmt, $raw)), 2);
    }

    /**
     * Soma líquida dos registros MED (contestação) — valores já movidos para pendente na carteira.
     */
    public static function totalMedHoldAmountForTenant(int $tenantId): float
    {
        if (! Schema::hasTable('wallet_transactions')) {
            return 0.0;
        }

        $sum = WalletTransaction::query()
            ->where('tenant_id', $tenantId)
            ->where('type', WalletTransaction::TYPE_MED_HOLD)
            ->sum('amount_net');

        return round((float) $sum, 2);
    }
}
