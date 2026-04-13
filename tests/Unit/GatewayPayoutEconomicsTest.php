<?php

namespace Tests\Unit;

use App\Services\Payout\GatewayPayoutEconomics;
use Tests\TestCase;

class GatewayPayoutEconomicsTest extends TestCase
{
    public function test_spacepag_credentials_sum_components(): void
    {
        $out = GatewayPayoutEconomics::fromCredentialsArray('spacepag', [
            'spacepag_payout_min_brl' => '7',
            'spacepag_admin_fee_pix_brl' => '1',
            'spacepag_admin_fee_payout_brl' => '0.5',
        ]);

        $this->assertSame(8.5, $out['required_min_net']);
        $this->assertSame(7.0, $out['payout_min_brl']);
        $this->assertSame(1.0, $out['admin_fee_pix_brl']);
        $this->assertSame(0.5, $out['admin_fee_payout_brl']);
    }

    public function test_transfer_amount_for_api_adds_admin_fee_payout(): void
    {
        $this->assertSame(18.0, GatewayPayoutEconomics::transferAmountBrlForApi(16.0, 2.0));
        $this->assertSame(100.0, GatewayPayoutEconomics::transferAmountBrlForApi(100.0, 0.0));
    }

    public function test_transfer_amount_for_api_ignores_negative_fee(): void
    {
        $this->assertSame(16.0, GatewayPayoutEconomics::transferAmountBrlForApi(16.0, -1.0));
    }
}
