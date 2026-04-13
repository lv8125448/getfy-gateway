<?php

namespace Tests\Feature;

use App\Events\OrderCompleted;
use App\Models\Order;
use App\Models\ProductCoproducer;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductCoproductionTest extends TestCase
{
    public function test_coproducer_invite_rejects_when_total_commission_exceeds_100(): void
    {
        if (! Schema::hasTable('product_coproducers')) {
            $this->markTestSkipped('product_coproducers table');
        }

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();

        $product = $this->createTestProduct(['tenant_id' => $seller->id]);

        ProductCoproducer::query()->create([
            'product_id' => $product->id,
            'inviter_user_id' => $seller->id,
            'email' => 'a@test.com',
            'status' => ProductCoproducer::STATUS_PENDING,
            'token' => str_repeat('a', 48),
            'commission_percent' => 60,
            'commission_on_direct_sales' => true,
            'commission_on_affiliate_sales' => false,
            'duration_preset' => ProductCoproducer::DURATION_ETERNAL,
        ]);

        $response = $this->actingAs($seller)->post(route('produtos.coproducers.store', $product->id), [
            'email' => 'b@test.com',
            'commission_percent' => 50,
            'commission_on_direct_sales' => true,
            'commission_on_affiliate_sales' => false,
            'duration_preset' => ProductCoproducer::DURATION_ETERNAL,
        ]);

        $response->assertSessionHasErrors('commission_percent');
    }

    public function test_order_completed_splits_wallet_between_seller_and_coproducer(): void
    {
        if (! Schema::hasTable('wallet_transactions') || ! Schema::hasTable('product_coproducers')) {
            $this->markTestSkipped('wallet or coproducers');
        }

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();

        $coproducer = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $coproducer->forceFill(['tenant_id' => $coproducer->id])->save();

        $product = $this->createTestProduct(['tenant_id' => $seller->id]);

        ProductCoproducer::query()->create([
            'product_id' => $product->id,
            'inviter_user_id' => $seller->id,
            'co_producer_user_id' => $coproducer->id,
            'email' => $coproducer->email,
            'status' => ProductCoproducer::STATUS_ACTIVE,
            'token' => str_repeat('b', 48),
            'commission_percent' => 30,
            'commission_on_direct_sales' => true,
            'commission_on_affiliate_sales' => false,
            'duration_preset' => ProductCoproducer::DURATION_ETERNAL,
            'starts_at' => now()->subMinute(),
            'ends_at' => null,
            'accepted_at' => now()->subMinute(),
        ]);

        $buyer = User::factory()->create(['role' => User::ROLE_ALUNO]);

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

        $sellerTx = WalletTransaction::query()
            ->where('order_id', $order->id)
            ->where('tenant_id', $seller->id)
            ->whereIn('type', [WalletTransaction::TYPE_CREDIT_SALE, WalletTransaction::TYPE_CREDIT_SALE_PENDING])
            ->get();

        $coproducerTx = WalletTransaction::query()
            ->where('order_id', $order->id)
            ->where('tenant_id', $coproducer->id)
            ->whereIn('type', [WalletTransaction::TYPE_CREDIT_SALE, WalletTransaction::TYPE_CREDIT_SALE_PENDING])
            ->get();

        $this->assertGreaterThan(0, $sellerTx->count());
        $this->assertGreaterThan(0, $coproducerTx->count());

        $sellerGross = round((float) $sellerTx->sum('amount_gross'), 2);
        $coproducerGross = round((float) $coproducerTx->sum('amount_gross'), 2);
        $this->assertEquals(100.0, $sellerGross + $coproducerGross);
        $this->assertEqualsWithDelta(30.0, $coproducerGross, 0.02);
        $this->assertEqualsWithDelta(70.0, $sellerGross, 0.02);
    }
}
