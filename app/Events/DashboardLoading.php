<?php

namespace App\Events;

use ArrayObject;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardLoading
{
    use Dispatchable, SerializesModels;

    /**
     * Plugins can add keys to this array (e.g. 'widgets', 'stats') to inject data into the dashboard.
     */
    public function __construct(public ArrayObject $data) {}
}
