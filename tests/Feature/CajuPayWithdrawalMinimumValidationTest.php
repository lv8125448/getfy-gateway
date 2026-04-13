<?php

namespace Tests\Feature;

use App\Models\GatewayCredential;
use App\Models\Setting;
use App\Models\TenantWallet;
use App\Models\User;
use App\Services\MerchantWithdrawalService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CajuPayWithdrawalMinimumValidationTest extends TestCase
{
    public function test_withdrawal_below_cajupay_min_net_fails(): void
    {
        if (! Schema::hasTable('withdrawals') || ! Schema::hasTable('tenant_wallets')) {
            $this->markTestSkipped('wallet/withdrawal tables');
        }

        Setting::set('merchant_fee_rules', [
            'pix' => ['percent' => 0, 'fixed' => 0],
            'card' => ['percent' => 0, 'fixed' => 0],
            'boleto' => ['percent' => 0, 'fixed' => 0],
            'withdrawal' => ['percent' => 0, 'fixed' => 4],
        ], null);

        $cred = GatewayCredential::query()->firstOrNew([
            'tenant_id' => null,
            'gateway_slug' => 'cajupay',
        ]);
        $cred->is_connected = true;
        $cred->setEncryptedCredentials([
            'public_key' => 'test-public',
            'secret_key' => 'test-secret',
            'cajupay_payout_min_brl' => '7',
            'cajupay_admin_fee_pix_brl' => '0',
            'cajupay_admin_fee_payout_brl' => '0',
        ]);
        $cred->save();

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();

        TenantWallet::query()->firstOrCreate(
            ['tenant_id' => $seller->id],
            [
                'available_balance' => 0,
                'pending_balance' => 0,
                'currency' => 'BRL',
                'available_pix' => 100,
                'available_card' => 0,
                'available_boleto' => 0,
                'pending_pix' => 0,
                'pending_card' => 0,
                'pending_boleto' => 0,
            ]
        );

        $this->expectException(ValidationException::class);

        try {
            MerchantWithdrawalService::requestWithdrawal($seller->fresh(), 10.0, 'pix', null);
        } finally {
            $cred->delete();
        }
    }
}
