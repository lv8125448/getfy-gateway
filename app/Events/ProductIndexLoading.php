<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductIndexLoading
{
    use Dispatchable, SerializesModels;

    public function __construct(public \ArrayObject $data) {}
}
