<?php

namespace Tests\Unit;

use App\Models\GatewayCredential;
use App\Models\Setting;
use App\Services\Payout\PlatformPayoutGateway;
use Tests\TestCase;

class PlatformPayoutGatewayTest extends TestCase
{
    public function test_cajupay_wins_when_both_connected(): void
    {
        foreach (['cajupay', 'spacepag'] as $slug) {
            $cred = GatewayCredential::query()->firstOrNew([
                'tenant_id' => null,
                'gateway_slug' => $slug,
            ]);
            $cred->is_connected = true;
            $cred->setEncryptedCredentials([
                'public_key' => 'pk_'.$slug,
                'secret_key' => 'sk_'.$slug,
            ]);
            $cred->save();
        }

        $this->assertSame('cajupay', PlatformPayoutGateway::activeSlug());

        GatewayCredential::query()->where('gateway_slug', 'cajupay')->delete();

        $this->assertSame('spacepag', PlatformPayoutGateway::activeSlug());

        GatewayCredential::query()->whereIn('gateway_slug', ['cajupay', 'spacepag'])->delete();
    }

    public function test_preference_spacepag_overrides_order_when_both_connected(): void
    {
        Setting::set('platform_payout_gateway', 'spacepag', null);

        foreach (['cajupay', 'spacepag'] as $slug) {
            $cred = GatewayCredential::query()->firstOrNew([
                'tenant_id' => null,
                'gateway_slug' => $slug,
            ]);
            $cred->is_connected = true;
            $cred->setEncryptedCredentials([
                'public_key' => 'pk_'.$slug,
                'secret_key' => 'sk_'.$slug,
            ]);
            $cred->save();
        }

        $this->assertSame('spacepag', PlatformPayoutGateway::activeSlug());
        $this->assertSame('spacepag', PlatformPayoutGateway::preference());

        GatewayCredential::query()->whereIn('gateway_slug', ['cajupay', 'spacepag'])->delete();
        Setting::set('platform_payout_gateway', null, null);
    }

    public function test_woovi_wins_when_only_woovi_connected(): void
    {
        $cred = GatewayCredential::query()->firstOrNew([
            'tenant_id' => null,
            'gateway_slug' => 'woovi',
        ]);
        $cred->is_connected = true;
        $cred->setEncryptedCredentials([
            'app_id' => 'app',
            'from_pix_key' => 'pix@test.com',
        ]);
        $cred->save();

        $this->assertSame('woovi', PlatformPayoutGateway::activeSlug());

        GatewayCredential::query()->where('gateway_slug', 'woovi')->delete();
    }

    public function test_preference_woovi_overrides_order_when_all_three_connected(): void
    {
        Setting::set('platform_payout_gateway', 'woovi', null);

        foreach (['cajupay', 'spacepag', 'woovi'] as $slug) {
            $cred = GatewayCredential::query()->firstOrNew([
                'tenant_id' => null,
                'gateway_slug' => $slug,
            ]);
            $cred->is_connected = true;
            if ($slug === 'woovi') {
                $cred->setEncryptedCredentials([
                    'app_id' => 'app',
                    'from_pix_key' => 'pix@test.com',
                ]);
            } else {
                $cred->setEncryptedCredentials([
                    'public_key' => 'pk_'.$slug,
                    'secret_key' => 'sk_'.$slug,
                ]);
            }
            $cred->save();
        }

        $this->assertSame('woovi', PlatformPayoutGateway::activeSlug());
        $this->assertSame('woovi', PlatformPayoutGateway::preference());

        GatewayCredential::query()->whereIn('gateway_slug', ['cajupay', 'spacepag', 'woovi'])->delete();
        Setting::set('platform_payout_gateway', null, null);
    }
}
