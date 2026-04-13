<?php

namespace Tests\Feature;

use App\Models\GatewayCredential;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WooviWebhookChargePaidTest extends TestCase
{
    public function test_webhook_completes_order_when_charge_completed_and_api_confirms(): void
    {
        Http::fake([
            'https://api.woovi.com/api/openpix/v1/charge/*' => Http::response([
                'charge' => [
                    'transactionID' => 'woovi-charge-1',
                    'status' => 'COMPLETED',
                ],
            ], 200),
        ]);

        $user = User::factory()->create(['tenant_id' => 1]);
        $product = $this->createTestProduct(['tenant_id' => 1]);

        $cred = GatewayCredential::query()->firstOrNew([
            'tenant_id' => null,
            'gateway_slug' => 'woovi',
        ]);
        $cred->is_connected = true;
        $cred->setEncryptedCredentials([
            'app_id' => 'test-app-id',
            'from_pix_key' => 'test@example.com',
        ]);
        $cred->save();

        $order = Order::create([
            'tenant_id' => 1,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'pending',
            'amount' => 10,
            'email' => 'buyer@test.com',
            'gateway' => 'woovi',
            'gateway_id' => 'woovi-charge-1',
        ]);

        $response = $this->postJson('/webhooks/gateways/woovi', [
            'event' => 'OPENPIX:CHARGE_COMPLETED',
            'charge' => [
                'transactionID' => 'woovi-charge-1',
                'status' => 'COMPLETED',
            ],
        ]);

        $response->assertOk()->assertJson(['received' => true]);
        $this->assertSame('completed', $order->fresh()->status);

        GatewayCredential::query()->where('gateway_slug', 'woovi')->delete();
    }
}
