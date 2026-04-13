<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BoletoGenerated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array{amount?: float, expire_at?: string|null, barcode?: string|null, pdf_url?: string|null}  $boletoData
     */
    public function __construct(
        public Order $order,
        public array $boletoData = []
    ) {}
}
