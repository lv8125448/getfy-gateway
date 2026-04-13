<?php

namespace Tests\Feature;

use App\Events\OrderCompleted;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\PaymentService;
use App\Services\SettlementReleaseService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MerchantSettlementAndGatewayTest extends TestCase
{
    public function test_payment_service_prefers_merchant_gateway_order_for_pix(): void
    {
        $seller = User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
        ]);
        $seller->forceFill([
            'tenant_id' => $seller->id,
            'merchant_gateway_order' => [
                'pix' => ['efi', 'cajupay'],
            ],
        ])->save();

        Setting::set('gateway_order', [
            'pix' => ['cajupay', 'efi', 'spacepag'],
            'card' => [],
            'boleto' => [],
            'pix_auto' => [],
        ], null);

        /** @var PaymentService $ps */
        $ps = app(PaymentService::class);
        $order = $ps->getGatewayOrderForMethod((int) $seller->id, 'pix');

        $this->assertNotEmpty($order);
        $this->assertSame('efi', $order[0]);
        $this->assertContains('cajupay', $order);
    }

    public function test_settlement_pending_created_when_delay_configured(): void
    {
        if (! Schema::hasTable('wallet_transactions')) {
            $this->markTestSkipped('wallet tables');
        }

        Setting::set('merchant_settlement_rules', [
            'pix' => ['days_to_available' => 2, 'reserve_percent' => 0],
            'card' => ['days_to_available' => 0, 'reserve_percent' => 0],
            'boleto' => ['days_to_available' => 0, 'reserve_percent' => 0],
        ], null);

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();

        $buyer = User::factory()->create(['role' => User::ROLE_ALUNO]);
        $product = $this->createTestProduct(['tenant_id' => $seller->id]);

        $order = Order::create([
            'tenant_id' => $seller->id,
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'status' => 'completed',
            'amount' => 100.00,
            'email' => $buyer->email,
            'payment_method' => 'pix',
            'metadata' => [],
        ]);

        event(new OrderCompleted($order->fresh()));

        $this->assertTrue(
            WalletTransaction::query()
                ->where('order_id', $order->id)
                ->where('type', WalletTransaction::TYPE_CREDIT_SALE_PENDING)
                ->exists()
        );
    }

    public function test_settlement_release_moves_pending_to_available(): void
    {
        if (! Schema::hasTable('wallet_transactions') || ! Schema::hasTable('tenant_wallets')) {
            $this->markTestSkipped('wallet tables');
        }

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();

        $buyer = User::factory()->create(['role' => User::ROLE_ALUNO]);
        $product = $this->createTestProduct(['tenant_id' => $seller->id]);

        // 1 dia = +24h no clears_at; com reserva o listener não zera tudo no mesmo instante.
        Setting::set('merchant_settlement_rules', [
            'pix' => ['days_to_available' => 1, 'reserve_percent' => 10],
            'card' => ['days_to_available' => 0, 'reserve_percent' => 0],
            'boleto' => ['days_to_available' => 0, 'reserve_percent' => 0],
        ], null);

        $order = Order::create([
            'tenant_id' => $seller->id,
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'status' => 'completed',
            'amount' => 100.00,
            'email' => $buyer->email,
            'payment_method' => 'pix',
            'metadata' => [],
        ]);

        event(new OrderCompleted($order->fresh()));

        $pending = WalletTransaction::query()
            ->where('order_id', $order->id)
            ->where('type', WalletTransaction::TYPE_CREDIT_SALE_PENDING)
            ->get();
        $this->assertGreaterThan(0, $pending->count());

        foreach ($pending as $tx) {
            $meta = $tx->meta;
            $meta['clears_at'] = now()->subMinute()->toIso8601String();
            $tx->meta = $meta;
            $tx->save();
        }

        $n = SettlementReleaseService::releaseDue(50);
        $this->assertGreaterThan(0, $n);

        $this->assertTrue(
            WalletTransaction::query()
                ->where('order_id', $order->id)
                ->where('type', WalletTransaction::TYPE_CREDIT_SALE)
                ->exists()
        );
    }
}
