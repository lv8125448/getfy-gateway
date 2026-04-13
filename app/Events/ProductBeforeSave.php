<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductBeforeSave
{
    use Dispatchable, SerializesModels;

    /**
     * If set by a listener, the save will be aborted with this message.
     */
    public ?string $abort = null;

    public function __construct(
        public Product $product,
        public array $validated,
        public bool $isCreate
    ) {}
}
