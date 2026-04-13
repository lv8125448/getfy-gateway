<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ScheduleHeartbeatCommand extends Command
{
    protected $signature = 'schedule:heartbeat';

    protected $description = 'Grava heartbeat no cache para indicar que o cron (schedule:run) está rodando.';

    public function handle(): int
    {
        Cache::put('schedule_heartbeat', now()->toIso8601String(), now()->addMinutes(5));

        return self::SUCCESS;
    }
}
