<?php

namespace Tests\Feature;

use App\Events\OrderCompleted;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAffiliateEnrollment;
use App\Models\ProductCoproducer;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\AffiliateConversionPixels;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductAffiliateTest extends TestCase
{
    public function test_affiliate_settings_rejects_when_coproducer_affiliate_sum_exceeds_100(): void
    {
        if (! Schema::hasColumn('products', 'affiliate_enabled')) {
            $this->markTestSkipped('affiliate columns');
        }

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();

        $product = $this->createTestProduct([
            'tenant_id' => $seller->id,
            'affiliate_enabled' => true,
            'affiliate_commission_percent' => 10,
            'affiliate_manual_approval' => true,
            'affiliate_show_in_showcase' => false,
        ]);

        ProductCoproducer::query()->create([
            'product_id' => $product->id,
            'inviter_user_id' => $seller->id,
            'email' => 'co@aff.test',
            'status' => ProductCoproducer::STATUS_PENDING,
            'token' => str_repeat('c', 48),
            'commission_percent' => 85,
            'commission_on_direct_sales' => false,
            'commission_on_affiliate_sales' => true,
            'duration_preset' => ProductCoproducer::DURATION_ETERNAL,
        ]);

        $response = $this->actingAs($seller)->put(route('produtos.affiliate-settings.update', $product->id), [
            'affiliate_enabled' => true,
            'affiliate_commission_percent' => 20,
            'affiliate_manual_approval' => true,
            'affiliate_show_in_showcase' => false,
            'affiliate_page_url' => null,
            'affiliate_support_email' => null,
            'affiliate_showcase_description' => null,
        ]);

        $response->assertSessionHasErrors('affiliate_commission_percent');
    }

    public function test_order_completed_splits_wallet_for_affiliate_sale(): void
    {
        if (! Schema::hasTable('wallet_transactions') || ! Schema::hasTable('product_affiliate_enrollments')) {
            $this->markTestSkipped('wallet or affiliate enrollments');
        }

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();

        $affiliate = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $affiliate->forceFill(['tenant_id' => $affiliate->id])->save();

        $product = $this->createTestProduct([
            'tenant_id' => $seller->id,
            'affiliate_enabled' => true,
            'affiliate_commission_percent' => 25,
            'affiliate_manual_approval' => false,
            'affiliate_show_in_showcase' => false,
        ]);

        $enrollment = ProductAffiliateEnrollment::query()->create([
            'product_id' => $product->id,
            'affiliate_user_id' => $affiliate->id,
            'status' => ProductAffiliateEnrollment::STATUS_APPROVED,
            'public_ref' => 'testref123456',
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
            'metadata' => [
                'affiliate_user_id' => $affiliate->id,
                'affiliate_enrollment_id' => $enrollment->id,
                'affiliate_ref' => 'testref123456',
            ],
        ]);

        event(new OrderCompleted($order->fresh()));

        $sellerTx = WalletTransaction::query()
            ->where('order_id', $order->id)
            ->where('tenant_id', $seller->id)
            ->whereIn('type', [WalletTransaction::TYPE_CREDIT_SALE, WalletTransaction::TYPE_CREDIT_SALE_PENDING])
            ->get();

        $affiliateTx = WalletTransaction::query()
            ->where('order_id', $order->id)
            ->where('tenant_id', $affiliate->id)
            ->whereIn('type', [WalletTransaction::TYPE_CREDIT_SALE, WalletTransaction::TYPE_CREDIT_SALE_PENDING])
            ->get();

        $this->assertGreaterThan(0, $sellerTx->count());
        $this->assertGreaterThan(0, $affiliateTx->count());

        $sellerGross = round((float) $sellerTx->sum('amount_gross'), 2);
        $affiliateGross = round((float) $affiliateTx->sum('amount_gross'), 2);
        $this->assertEquals(100.0, $sellerGross + $affiliateGross);
        $this->assertEqualsWithDelta(25.0, $affiliateGross, 0.02);
        $this->assertEqualsWithDelta(75.0, $sellerGross, 0.02);
    }

    public function test_showcase_lists_only_marked_products(): void
    {
        if (! Schema::hasColumn('products', 'affiliate_show_in_showcase')) {
            $this->markTestSkipped('affiliate columns');
        }

        $viewer = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $viewer->forceFill(['tenant_id' => $viewer->id])->save();

        $other = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $other->forceFill(['tenant_id' => $other->id])->save();

        $this->createTestProduct([
            'tenant_id' => $other->id,
            'name' => 'Hidden',
            'affiliate_enabled' => true,
            'affiliate_show_in_showcase' => false,
            'is_active' => true,
        ]);

        $visible = $this->createTestProduct([
            'tenant_id' => $other->id,
            'name' => 'Visible Showcase',
            'affiliate_enabled' => true,
            'affiliate_show_in_showcase' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($viewer)->get(route('produtos.vitrine-afiliacao'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $visible->id));
    }

    public function test_affiliate_products_page_lists_approved_enrollment(): void
    {
        if (! Schema::hasTable('product_affiliate_enrollments')) {
            $this->markTestSkipped('affiliate enrollments');
        }

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();

        $affiliate = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $affiliate->forceFill(['tenant_id' => $affiliate->id])->save();

        $product = $this->createTestProduct([
            'tenant_id' => $seller->id,
            'name' => 'Curso Afiliado Test',
            'checkout_slug' => 'curso-afiliado-test',
            'is_active' => true,
        ]);

        ProductAffiliateEnrollment::query()->create([
            'product_id' => $product->id,
            'affiliate_user_id' => $affiliate->id,
            'status' => ProductAffiliateEnrollment::STATUS_APPROVED,
            'public_ref' => 'reftest'.substr(uniqid('', true), 0, 8),
        ]);

        $response = $this->actingAs($affiliate)->get(route('produtos.afiliados.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Produtos/Afiliados')
            ->has('affiliate_products', 1)
            ->where('affiliate_products.0.name', 'Curso Afiliado Test')
            ->where('affiliate_products.0.status', 'approved'));
    }

    public function test_affiliate_cannot_edit_product_but_can_open_affiliate_panel(): void
    {
        if (! Schema::hasTable('product_affiliate_enrollments')) {
            $this->markTestSkipped('affiliate enrollments');
        }

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();

        $affiliate = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $affiliate->forceFill(['tenant_id' => $affiliate->id])->save();

        $product = $this->createTestProduct([
            'tenant_id' => $seller->id,
            'is_active' => true,
        ]);

        ProductAffiliateEnrollment::query()->create([
            'product_id' => $product->id,
            'affiliate_user_id' => $affiliate->id,
            'status' => ProductAffiliateEnrollment::STATUS_APPROVED,
            'public_ref' => 'panref'.substr(uniqid('', true), 0, 8),
        ]);

        $this->actingAs($affiliate)->get(route('produtos.edit', $product->id))->assertForbidden();

        $this->actingAs($affiliate)->get(route('produtos.painel-afiliado.show', $product->id))->assertOk()->assertInertia(
            fn ($page) => $page->component('Produtos/PainelAfiliado')
        );
    }

    public function test_affiliate_panel_put_updates_enrollment_conversion_pixels(): void
    {
        if (! Schema::hasTable('product_affiliate_enrollments') || ! Schema::hasColumn('product_affiliate_enrollments', 'conversion_pixels')) {
            $this->markTestSkipped('conversion_pixels on enrollments');
        }

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();

        $affiliate = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $affiliate->forceFill(['tenant_id' => $affiliate->id])->save();

        $product = $this->createTestProduct([
            'tenant_id' => $seller->id,
            'is_active' => true,
        ]);

        $enrollment = ProductAffiliateEnrollment::query()->create([
            'product_id' => $product->id,
            'affiliate_user_id' => $affiliate->id,
            'status' => ProductAffiliateEnrollment::STATUS_APPROVED,
            'public_ref' => 'putref'.substr(uniqid('', true), 0, 8),
        ]);

        $payload = [
            'conversion_pixels' => [
                'meta' => [
                    'enabled' => true,
                    'entries' => [
                        [
                            'id' => 'test-pixel-id',
                            'pixel_id' => '999888777',
                            'access_token' => '',
                            'fire_purchase_on_pix' => true,
                            'fire_purchase_on_boleto' => true,
                            'disable_order_bump_events' => false,
                        ],
                    ],
                ],
                'tiktok' => ['enabled' => false, 'entries' => []],
                'google_ads' => ['enabled' => false, 'entries' => []],
                'google_analytics' => ['enabled' => false, 'entries' => []],
                'custom_script' => [],
            ],
        ];

        $this->actingAs($affiliate)->put(route('produtos.painel-afiliado.update', $product->id), $payload)->assertRedirect();

        $enrollment->refresh();
        $this->assertIsArray($enrollment->conversion_pixels);
        $this->assertEquals('999888777', $enrollment->conversion_pixels['meta']['entries'][0]['pixel_id'] ?? null);
    }

    public function test_for_order_uses_affiliate_enrollment_pixels_not_product(): void
    {
        if (! Schema::hasTable('product_affiliate_enrollments') || ! Schema::hasColumn('product_affiliate_enrollments', 'conversion_pixels')) {
            $this->markTestSkipped('conversion_pixels on enrollments');
        }

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();

        $affiliate = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $affiliate->forceFill(['tenant_id' => $affiliate->id])->save();

        $product = $this->createTestProduct([
            'tenant_id' => $seller->id,
            'is_active' => true,
            'conversion_pixels' => [
                'meta' => [
                    'enabled' => true,
                    'entries' => [
                        [
                            'id' => 'p1',
                            'pixel_id' => 'PIXEL_FROM_SELLER',
                            'access_token' => '',
                            'fire_purchase_on_pix' => true,
                            'fire_purchase_on_boleto' => true,
                            'disable_order_bump_events' => false,
                        ],
                    ],
                ],
                'tiktok' => ['enabled' => false, 'entries' => []],
                'google_ads' => ['enabled' => false, 'entries' => []],
                'google_analytics' => ['enabled' => false, 'entries' => []],
                'custom_script' => [],
            ],
        ]);

        $enrollment = ProductAffiliateEnrollment::query()->create([
            'product_id' => $product->id,
            'affiliate_user_id' => $affiliate->id,
            'status' => ProductAffiliateEnrollment::STATUS_APPROVED,
            'public_ref' => 'ordref'.substr(uniqid('', true), 0, 8),
            'conversion_pixels' => [
                'meta' => [
                    'enabled' => true,
                    'entries' => [
                        [
                            'id' => 'a1',
                            'pixel_id' => 'PIXEL_FROM_AFFILIATE',
                            'access_token' => '',
                            'fire_purchase_on_pix' => true,
                            'fire_purchase_on_boleto' => true,
                            'disable_order_bump_events' => false,
                        ],
                    ],
                ],
                'tiktok' => ['enabled' => false, 'entries' => []],
                'google_ads' => ['enabled' => false, 'entries' => []],
                'google_analytics' => ['enabled' => false, 'entries' => []],
                'custom_script' => [],
            ],
        ]);

        $buyer = User::factory()->create(['role' => User::ROLE_ALUNO]);

        $order = Order::create([
            'tenant_id' => $seller->id,
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'status' => 'completed',
            'amount' => 50.00,
            'email' => $buyer->email,
            'payment_method' => 'pix',
            'metadata' => [
                'affiliate_enrollment_id' => $enrollment->id,
            ],
        ]);

        $order->load('product');
        $pixels = AffiliateConversionPixels::forOrder($order);

        $this->assertSame(
            'PIXEL_FROM_AFFILIATE',
            $pixels['meta']['entries'][0]['pixel_id'] ?? null
        );
    }

    public function test_for_product_and_ref_uses_enrollment_pixels_when_ref_valid(): void
    {
        if (! Schema::hasTable('product_affiliate_enrollments') || ! Schema::hasColumn('product_affiliate_enrollments', 'conversion_pixels')) {
            $this->markTestSkipped('conversion_pixels on enrollments');
        }

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();

        $affiliate = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $affiliate->forceFill(['tenant_id' => $affiliate->id])->save();

        $product = $this->createTestProduct([
            'tenant_id' => $seller->id,
            'is_active' => true,
            'conversion_pixels' => [
                'meta' => [
                    'enabled' => true,
                    'entries' => [
                        [
                            'id' => 'p1',
                            'pixel_id' => 'SELLER_ONLY',
                            'access_token' => '',
                            'fire_purchase_on_pix' => true,
                            'fire_purchase_on_boleto' => true,
                            'disable_order_bump_events' => false,
                        ],
                    ],
                ],
                'tiktok' => ['enabled' => false, 'entries' => []],
                'google_ads' => ['enabled' => false, 'entries' => []],
                'google_analytics' => ['enabled' => false, 'entries' => []],
                'custom_script' => [],
            ],
        ]);

        $ref = 'chkref'.substr(uniqid('', true), 0, 8);
        ProductAffiliateEnrollment::query()->create([
            'product_id' => $product->id,
            'affiliate_user_id' => $affiliate->id,
            'status' => ProductAffiliateEnrollment::STATUS_APPROVED,
            'public_ref' => $ref,
            'conversion_pixels' => [
                'meta' => [
                    'enabled' => true,
                    'entries' => [
                        [
                            'id' => 'a1',
                            'pixel_id' => 'AFFILIATE_CHECKOUT',
                            'access_token' => '',
                            'fire_purchase_on_pix' => true,
                            'fire_purchase_on_boleto' => true,
                            'disable_order_bump_events' => false,
                        ],
                    ],
                ],
                'tiktok' => ['enabled' => false, 'entries' => []],
                'google_ads' => ['enabled' => false, 'entries' => []],
                'google_analytics' => ['enabled' => false, 'entries' => []],
                'custom_script' => [],
            ],
        ]);

        $fresh = Product::query()->find($product->id);
        $pixels = AffiliateConversionPixels::forProductAndRef($fresh, $ref);

        $this->assertSame('AFFILIATE_CHECKOUT', $pixels['meta']['entries'][0]['pixel_id'] ?? null);
    }
}
