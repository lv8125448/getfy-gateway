<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class PublicCheckoutSecurityTest extends TestCase
{
    public function test_public_checkout_rejects_manual_payment_method(): void
    {
        User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
            'tenant_id' => 1,
        ]);

        $product = $this->createTestProduct([
            'name' => 'Produto teste segurança',
            'price' => 99.90,
            'checkout_config' => [
                'customer_fields' => [
                    'name' => false,
                    'cpf' => false,
                    'phone' => false,
                    'coupon' => false,
                ],
            ],
        ]);

        $response = $this->postJson('/checkout', [
            'product_id' => $product->id,
            'payment_method' => 'manual',
            'email' => 'buyer@example.com',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('orders', [
            'product_id' => $product->id,
            'status' => 'completed',
            'gateway' => 'manual',
        ]);
    }

    public function test_public_checkout_requires_payment_method(): void
    {
        User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
            'tenant_id' => 1,
        ]);

        $product = $this->createTestProduct([
            'name' => 'Produto teste segurança 2',
            'price' => 49.90,
            'checkout_config' => [
                'customer_fields' => [
                    'name' => false,
                    'cpf' => false,
                    'phone' => false,
                    'coupon' => false,
                ],
            ],
        ]);

        $response = $this->postJson('/checkout', [
            'product_id' => $product->id,
            'email' => 'buyer2@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_method']);
    }
}
