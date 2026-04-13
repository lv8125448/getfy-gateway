<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PixGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public array $pixData = []
    ) {}
}
