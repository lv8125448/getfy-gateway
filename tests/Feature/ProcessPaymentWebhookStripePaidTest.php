<?php

namespace Tests\Feature;

use App\Events\OrderCompleted;
use App\Gateways\Stripe\StripeDriver;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\GatewayCredential;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProcessPaymentWebhookStripePaidTest extends TestCase
{
    public function test_stripe_payment_intent_succeeded_completes_order_and_dispatches_order_completed(): void
    {
        Event::fake([OrderCompleted::class]);

        $this->mock(StripeDriver::class, function ($mock) {
            $mock->shouldReceive('getTransactionStatus')
                ->once()
                ->andReturn('paid');
        });

        $user = User::factory()->create(['tenant_id' => 1]);
        $product = $this->createTestProduct(['name' => 'Stripe product']);

        $cred = new GatewayCredential([
            'tenant_id' => 1,
            'gateway_slug' => 'stripe',
            'is_connected' => true,
        ]);
        $cred->setEncryptedCredentials(['secret_key' => 'sk_test_fake']);
        $cred->save();

        $order = Order::create([
            'tenant_id' => 1,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'pending',
            'amount' => 10,
            'email' => 'buyer-stripe@example.com',
            'gateway' => 'stripe',
            'gateway_id' => 'pi_test_123',
        ]);

        ProcessPaymentWebhook::dispatchSync('stripe', 'pi_test_123', 'payment_intent.succeeded', 'paid', []);

        $this->assertSame('completed', $order->fresh()->status);
        Event::assertDispatched(OrderCompleted::class);
    }
}
