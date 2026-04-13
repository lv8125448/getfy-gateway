<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\CajuPay\CajuPayCredentialEconomics;
use App\Services\EffectiveMerchantFees;
use Tests\TestCase;

class CajuPayWithdrawalEconomicsTest extends TestCase
{
    public function test_minimum_withdrawal_gross_matches_fixed_fee_plus_target_net(): void
    {
        Setting::set('merchant_fee_rules', [
            'pix' => ['percent' => 0, 'fixed' => 0],
            'card' => ['percent' => 0, 'fixed' => 0],
            'boleto' => ['percent' => 0, 'fixed' => 0],
            'withdrawal' => ['percent' => 0, 'fixed' => 4],
        ], null);

        $minGross = EffectiveMerchantFees::minimumWithdrawalGrossForTargetNet(1, 7.0);
        $this->assertSame(11.0, $minGross);

        $fee = EffectiveMerchantFees::calculateWithdrawalFee(1, 11.0);
        $this->assertSame(7.0, $fee['net']);
    }

    public function test_credential_economics_sums_components(): void
    {
        $out = CajuPayCredentialEconomics::fromCredentialsArray([
            'public_key' => 'x',
            'secret_key' => 'y',
            'cajupay_payout_min_brl' => '7',
            'cajupay_admin_fee_pix_brl' => '1',
            'cajupay_admin_fee_payout_brl' => '0.5',
        ]);

        $this->assertSame(8.5, $out['required_min_net']);
        $this->assertSame(7.0, $out['cajupay_payout_min_brl']);
        $this->assertSame(1.0, $out['cajupay_admin_fee_pix_brl']);
        $this->assertSame(0.5, $out['cajupay_admin_fee_payout_brl']);
    }

    public function test_credential_economics_defaults_min_when_empty(): void
    {
        $out = CajuPayCredentialEconomics::fromCredentialsArray([
            'cajupay_payout_min_brl' => '',
            'cajupay_admin_fee_pix_brl' => '',
            'cajupay_admin_fee_payout_brl' => '',
        ]);

        $this->assertSame(7.0, $out['required_min_net']);
    }
}
