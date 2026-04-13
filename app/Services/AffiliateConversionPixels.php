<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAffiliateEnrollment;

/**
 * Pixels exibidos no checkout quando a visita/venda é por link de afiliado:
 * apenas os configurados no enrollment (nunca os do produto do produtor).
 */
class AffiliateConversionPixels
{
    /**
     * Pixels para a página inicial do checkout quando há ?ref= válido (afiliado aprovado).
     */
    public static function forProductAndRef(Product $product, ?string $ref): array
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return self::productPixels($product);
        }

        $enrollment = ProductAffiliateEnrollment::findApprovedByRefForProduct($ref, $product);
        if ($enrollment === null) {
            return self::productPixels($product);
        }

        return self::enrollmentPixelsOrEmpty($enrollment);
    }

    /**
     * Pixels após o pedido (obrigado, PIX, boleto) quando a venda foi de afiliado.
     */
    public static function forOrder(Order $order): array
    {
        $meta = $order->metadata ?? [];
        $enrollmentId = isset($meta['affiliate_enrollment_id']) ? (int) $meta['affiliate_enrollment_id'] : 0;
        if ($enrollmentId <= 0) {
            $product = $order->relationLoaded('product') ? $order->product : $order->product()->first();
            if (! $product) {
                return Product::defaultConversionPixels();
            }

            return self::productPixels($product);
        }

        $enrollment = ProductAffiliateEnrollment::query()->find($enrollmentId);
        if ($enrollment === null || $enrollment->status !== ProductAffiliateEnrollment::STATUS_APPROVED) {
            $product = $order->relationLoaded('product') ? $order->product : $order->product()->first();
            if (! $product) {
                return Product::defaultConversionPixels();
            }

            return self::productPixels($product);
        }

        return self::enrollmentPixelsOrEmpty($enrollment);
    }

    /**
     * @return array<string, mixed>
     */
    private static function productPixels(Product $product): array
    {
        return $product->conversion_pixels ?? Product::defaultConversionPixels();
    }

    /**
     * @return array<string, mixed>
     */
    private static function enrollmentPixelsOrEmpty(ProductAffiliateEnrollment $enrollment): array
    {
        $defaults = Product::defaultConversionPixels();
        $raw = $enrollment->conversion_pixels;
        if (! is_array($raw)) {
            return $defaults;
        }

        return array_replace_recursive($defaults, $raw);
    }
}
