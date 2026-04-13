<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Models\TenantWallet;
use App\Models\User;
use App\Services\MerchantWalletAdminBlockService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlatformMerchantBlockTest extends TestCase
{
    public function test_suspended_infoprodutor_cannot_log_in_to_seller_panel(): void
    {
        $this->withoutMiddleware(EnsureInstalled::class);

        $merchant = User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
            'account_status' => 'suspended',
        ]);
        $merchant->forceFill(['tenant_id' => $merchant->id])->save();

        $response = $this->post('/login', [
            'email' => $merchant->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_effective_available_zero_when_admin_blocks_withdrawals(): void
    {
        if (! Schema::hasTable('tenant_wallets') || ! Schema::hasColumn('tenant_wallets', 'admin_withdrawal_blocked')) {
            $this->markTestSkipped('tenant_wallets admin columns');
        }

        $wallet = new TenantWallet([
            'tenant_id' => 999001,
            'available_pix' => 100.00,
            'available_card' => 0,
            'available_boleto' => 0,
            'pending_pix' => 0,
            'pending_card' => 0,
            'pending_boleto' => 0,
            'available_balance' => 100,
            'pending_balance' => 0,
            'currency' => 'BRL',
            'admin_withdrawal_blocked' => true,
        ]);
        $wallet->save();

        $eff = MerchantWalletAdminBlockService::effectiveAvailableForWithdrawal($wallet->fresh(), 'pix');
        $this->assertEquals(0.0, $eff);
    }

    public function test_effective_available_reduced_by_admin_blocked_amount(): void
    {
        if (! Schema::hasTable('tenant_wallets') || ! Schema::hasColumn('tenant_wallets', 'admin_blocked_amount')) {
            $this->markTestSkipped('tenant_wallets admin columns');
        }

        $wallet = new TenantWallet([
            'tenant_id' => 999002,
            'available_pix' => 100.00,
            'available_card' => 0,
            'available_boleto' => 0,
            'pending_pix' => 0,
            'pending_card' => 0,
            'pending_boleto' => 0,
            'available_balance' => 100,
            'pending_balance' => 0,
            'currency' => 'BRL',
            'admin_withdrawal_blocked' => false,
            'admin_blocked_amount' => 30.00,
        ]);
        $wallet->save();

        $eff = MerchantWalletAdminBlockService::effectiveAvailableForWithdrawal($wallet->fresh(), 'pix');
        $this->assertEqualsWithDelta(70.0, $eff, 0.01);
    }

}
