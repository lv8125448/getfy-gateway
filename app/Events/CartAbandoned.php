<?php

namespace App\Events;

use App\Models\CheckoutSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartAbandoned
{
    use Dispatchable, SerializesModels;

    public function __construct(public CheckoutSession $checkoutSession) {}
}
