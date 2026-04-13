<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlatformDashboardRevenueTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_faturamento_counts_credit_sale_only_when_pending_and_sale_exist(): void
    {
        if (! Schema::hasTable('wallet_transactions')) {
            $this->markTestSkipped('wallet tables');
        }

        Carbon::setTestNow(Carbon::parse('2026-04-13 14:00:00'));

        $admin = User::factory()->create([
            'role' => User::ROLE_PLATFORM_ADMIN,
            'tenant_id' => null,
        ]);
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
            'gateway' => 'efi',
            'metadata' => [],
        ]);

        WalletTransaction::query()->create([
            'tenant_id' => $seller->id,
            'order_id' => $order->id,
            'bucket' => 'pix',
            'type' => WalletTransaction::TYPE_CREDIT_SALE_PENDING,
            'amount_gross' => 100,
            'amount_fee' => 10.00,
            'amount_net' => 90,
            'meta' => ['released_at' => Carbon::now()->toIso8601String()],
        ]);
        WalletTransaction::query()->create([
            'tenant_id' => $seller->id,
            'order_id' => $order->id,
            'bucket' => 'pix',
            'type' => WalletTransaction::TYPE_CREDIT_SALE,
            'amount_gross' => 100,
            'amount_fee' => 10.00,
            'amount_net' => 90,
            'meta' => [],
        ]);

        $this->actingAs($admin)
            ->get('/plataforma/dashboard?period=hoje')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('kpis.faturamento_taxas_cobradas', 10.0));
    }

    public function test_faturamento_sums_pending_parts_when_no_credit_sale(): void
    {
        if (! Schema::hasTable('wallet_transactions')) {
            $this->markTestSkipped('wallet tables');
        }

        Carbon::setTestNow(Carbon::parse('2026-04-13 14:00:00'));

        $admin = User::factory()->create([
            'role' => User::ROLE_PLATFORM_ADMIN,
            'tenant_id' => null,
        ]);
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
            'gateway' => 'efi',
            'metadata' => [],
        ]);

        WalletTransaction::query()->create([
            'tenant_id' => $seller->id,
            'order_id' => $order->id,
            'bucket' => 'pix',
            'type' => WalletTransaction::TYPE_CREDIT_SALE_PENDING,
            'amount_gross' => 60,
            'amount_fee' => 4.00,
            'amount_net' => 56,
            'meta' => ['portion' => 'main'],
        ]);
        WalletTransaction::query()->create([
            'tenant_id' => $seller->id,
            'order_id' => $order->id,
            'bucket' => 'pix',
            'type' => WalletTransaction::TYPE_CREDIT_SALE_PENDING,
            'amount_gross' => 40,
            'amount_fee' => 6.00,
            'amount_net' => 34,
            'meta' => ['portion' => 'reserve'],
        ]);

        $this->actingAs($admin)
            ->get('/plataforma/dashboard?period=hoje')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('kpis.faturamento_taxas_cobradas', 10.0));
    }

    public function test_faturamento_respects_period_filter(): void
    {
        if (! Schema::hasTable('wallet_transactions')) {
            $this->markTestSkipped('wallet tables');
        }

        Carbon::setTestNow(Carbon::parse('2026-04-13 14:00:00'));

        $admin = User::factory()->create([
            'role' => User::ROLE_PLATFORM_ADMIN,
            'tenant_id' => null,
        ]);
        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();
        $buyer = User::factory()->create(['role' => User::ROLE_ALUNO]);
        $product = $this->createTestProduct(['tenant_id' => $seller->id]);

        $order = Order::create([
            'tenant_id' => $seller->id,
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'status' => 'completed',
            'amount' => 50.00,
            'email' => $buyer->email,
            'payment_method' => 'pix',
            'gateway' => 'efi',
            'metadata' => [],
        ]);
        Order::query()->whereKey($order->id)->update([
            'created_at' => Carbon::now()->subDay(),
            'updated_at' => Carbon::now()->subDay(),
        ]);

        WalletTransaction::query()->create([
            'tenant_id' => $seller->id,
            'order_id' => $order->id,
            'bucket' => 'pix',
            'type' => WalletTransaction::TYPE_CREDIT_SALE,
            'amount_gross' => 50,
            'amount_fee' => 99.00,
            'amount_net' => 40,
            'meta' => [],
        ]);

        $this->actingAs($admin)
            ->get('/plataforma/dashboard?period=hoje')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('kpis.faturamento_taxas_cobradas', 0.0));
    }
}
