<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutConfigOfferPlanInheritanceTest extends TestCase
{
    public function test_offer_checkout_config_update_does_not_persist_product_only_payment_keys(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
            'tenant_id' => 1,
        ]);

        $product = $this->createTestProduct([
            'checkout_config' => [
                'payment_gateways' => [
                    'pix' => 'efi',
                    'pix_redundancy' => [],
                    'card' => null,
                    'card_redundancy' => [],
                    'boleto' => null,
                    'boleto_redundancy' => [],
                    'pix_auto' => null,
                    'pix_auto_redundancy' => [],
                    'crypto' => null,
                    'crypto_redundancy' => [],
                ],
            ],
        ]);

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'name' => 'Oferta A',
            'price' => 20,
            'currency' => 'BRL',
            'checkout_slug' => Str::lower(Str::random(7)),
            'position' => 1,
        ]);

        $response = $this->actingAs($user)->put("/produtos/{$product->id}/checkout-config", [
            'offer_id' => $offer->id,
            'plan_id' => null,
            'config' => [
                'template' => 'original',
            ],
        ]);

        $response->assertRedirect();
        $offer->refresh();
        $stored = $offer->checkout_config ?? [];
        $this->assertIsArray($stored);
        $this->assertArrayNotHasKey('payment_gateways', $stored);
        $this->assertArrayNotHasKey('card_installments', $stored);
        $this->assertArrayNotHasKey('stripe_link_enabled', $stored);
        $this->assertArrayNotHasKey('payment_methods_enabled', $stored);
        $this->assertArrayNotHasKey('deliverable_link', $stored);
        $this->assertArrayNotHasKey('email_template', $stored);

        $product->refresh();
        $this->assertSame('efi', $product->checkout_config['payment_gateways']['pix'] ?? null);
    }

    public function test_plan_checkout_config_update_does_not_persist_product_only_payment_keys(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
            'tenant_id' => 1,
        ]);

        $product = $this->createTestProduct([
            'billing_type' => Product::BILLING_SUBSCRIPTION,
            'checkout_config' => [
                'payment_gateways' => [
                    'pix' => 'mercadopago',
                    'pix_redundancy' => [],
                    'card' => null,
                    'card_redundancy' => [],
                    'boleto' => null,
                    'boleto_redundancy' => [],
                    'pix_auto' => null,
                    'pix_auto_redundancy' => [],
                    'crypto' => null,
                    'crypto_redundancy' => [],
                ],
            ],
        ]);

        $plan = SubscriptionPlan::create([
            'product_id' => $product->id,
            'name' => 'Mensal',
            'price' => 30,
            'currency' => 'BRL',
            'interval' => SubscriptionPlan::INTERVAL_MONTHLY,
            'checkout_slug' => Str::lower(Str::random(7)),
            'position' => 0,
        ]);

        $response = $this->actingAs($user)->put("/produtos/{$product->id}/checkout-config", [
            'offer_id' => null,
            'plan_id' => $plan->id,
            'config' => [
                'template' => 'original',
            ],
        ]);

        $response->assertRedirect();
        $plan->refresh();
        $stored = $plan->checkout_config ?? [];
        $this->assertIsArray($stored);
        $this->assertArrayNotHasKey('payment_gateways', $stored);
        $this->assertArrayNotHasKey('payment_methods_enabled', $stored);

        $product->refresh();
        $this->assertSame('mercadopago', $product->checkout_config['payment_gateways']['pix'] ?? null);
    }
}
