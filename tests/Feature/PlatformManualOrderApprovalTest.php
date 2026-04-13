<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlatformManualOrderApprovalTest extends TestCase
{
    public function test_platform_admin_can_approve_pending_order_and_credits_wallet(): void
    {
        $seller = User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
        ]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();

        $buyer = User::factory()->create(['role' => User::ROLE_ALUNO]);

        $product = $this->createTestProduct([
            'tenant_id' => $seller->id,
        ]);

        $order = Order::create([
            'tenant_id' => $seller->id,
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'status' => 'pending',
            'amount' => 100.00,
            'email' => $buyer->email,
            'payment_method' => 'pix',
            'metadata' => [],
        ]);

        $admin = User::factory()->create([
            'role' => User::ROLE_PLATFORM_ADMIN,
            'tenant_id' => null,
        ]);

        $response = $this->actingAs($admin)->post(route('plataforma.transacoes.pedidos.approve-manual', $order));

        $response->assertRedirect(route('plataforma.transacoes.index'));
        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertTrue($order->approved_manually);

        if (Schema::hasTable('wallet_transactions')) {
            $this->assertTrue(
                WalletTransaction::query()
                    ->where('order_id', $order->id)
                    ->where('type', WalletTransaction::TYPE_CREDIT_SALE)
                    ->exists()
            );
        }
    }

    public function test_infoprodutor_gets_403_on_legacy_vendas_manual_approval(): void
    {
        $seller = User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
        ]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();

        $buyer = User::factory()->create(['role' => User::ROLE_ALUNO]);

        $product = $this->createTestProduct([
            'tenant_id' => $seller->id,
        ]);

        $order = Order::create([
            'tenant_id' => $seller->id,
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'status' => 'pending',
            'amount' => 50.00,
            'email' => $buyer->email,
            'payment_method' => 'pix',
            'metadata' => [],
        ]);

        $response = $this->actingAs($seller)->postJson(route('vendas.approve-manually', $order));

        $response->assertStatus(403);
        $response->assertJsonFragment(['success' => false]);
    }
}
