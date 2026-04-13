<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CheckoutPageLoading
{
    use Dispatchable, SerializesModels;

    /**
     * @param  \ArrayObject{array{product: array, config: array}}  $data
     */
    public function __construct(
        public Product $product,
        public \ArrayObject $data
    ) {}
}
