<?php

namespace Tests\Feature;

use App\Models\GatewayCredential;
use App\Models\Setting;
use App\Services\PaymentService;
use Tests\TestCase;

class PaymentMethodsPerProductTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('gateway_order', [
            'pix' => ['efi'],
            'card' => [],
            'boleto' => [],
            'pix_auto' => [],
        ], null);
    }

    public function test_available_methods_excludes_pix_when_disabled_on_product(): void
    {
        $product = $this->createTestProduct([
            'tenant_id' => 1,
            'checkout_config' => [
                'payment_methods_enabled' => [
                    'pix' => false,
                    'card' => false,
                    'boleto' => false,
                    'pix_auto' => false,
                ],
            ],
        ]);

        $cred = new GatewayCredential([
            'tenant_id' => 1,
            'gateway_slug' => 'efi',
            'is_connected' => true,
        ]);
        $cred->setEncryptedCredentials(['payee_code' => '123', 'sandbox' => true]);
        $cred->save();

        $methods = app(PaymentService::class)->availablePaymentMethodsForCheckout($product, null, null);
        $ids = array_column($methods, 'id');
        $this->assertNotContains('pix', $ids);
    }

    public function test_available_methods_includes_pix_when_enabled_on_product(): void
    {
        $product = $this->createTestProduct([
            'tenant_id' => 1,
            'checkout_config' => [
                'payment_methods_enabled' => [
                    'pix' => true,
                    'card' => false,
                    'boleto' => false,
                    'pix_auto' => false,
                ],
            ],
        ]);

        $cred = new GatewayCredential([
            'tenant_id' => 1,
            'gateway_slug' => 'efi',
            'is_connected' => true,
        ]);
        $cred->setEncryptedCredentials(['payee_code' => '123', 'sandbox' => true]);
        $cred->save();

        $methods = app(PaymentService::class)->availablePaymentMethodsForCheckout($product, null, null);
        $ids = array_column($methods, 'id');
        $this->assertContains('pix', $ids);
    }
}
