<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Services\OrderCompletedWalletCreditor;

class CreditTenantWalletOnOrderCompleted
{
    public function handle(OrderCompleted $event): void
    {
        OrderCompletedWalletCreditor::credit($event->order);
    }
}
