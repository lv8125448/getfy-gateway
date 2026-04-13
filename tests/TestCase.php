<?php

namespace Tests;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Em SQLite (phpunit) a coluna products.id continua inteira; o boot do model usaria UUID (migração só no MySQL).
     */
    protected function createTestProduct(array $overrides = []): Product
    {
        $nextId = (int) (Product::query()->max('id') ?? 0) + 1;

        $product = new Product;
        $product->forceFill(array_merge([
            'id' => (string) $nextId,
            'tenant_id' => 1,
            'name' => 'Test product',
            'slug' => 't-'.uniqid('', true),
            'type' => Product::TYPE_LINK,
            'billing_type' => Product::BILLING_ONE_TIME,
            'price' => 10,
            'currency' => 'BRL',
            'is_active' => true,
        ], $overrides));
        $product->save();

        return $product->fresh();
    }
}
