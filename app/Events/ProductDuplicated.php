<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductDuplicated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Product $original,
        public Product $newProduct
    ) {}
}
