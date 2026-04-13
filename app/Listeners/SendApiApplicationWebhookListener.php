<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Events\OrderPending;
use App\Events\OrderRefunded;
use App\Jobs\SendApiApplicationWebhookJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class SendApiApplicationWebhookListener
{
    public function handleOrderCompleted(OrderCompleted $event): void
    {
        $order = $event->order;
        if ($order->api_application_id === null) {
            return;
        }
        $job = new SendApiApplicationWebhookJob($order->id, 'order.completed');
        if ($this->shouldDispatchSyncForCompleted()) {
            $job->handle();
        } else {
            dispatch($job);
        }
    }

    public function handleOrderPending(OrderPending $event): void
    {
        $order = $event->order;
        if ($order->api_application_id === null) {
            return;
        }
        dispatch(new SendApiApplicationWebhookJob($order->id, 'order.pending'));
    }

    public function handleOrderRefunded(OrderRefunded $event): void
    {
        $order = $event->order;
        if ($order->api_application_id === null) {
            return;
        }
        dispatch(new SendApiApplicationWebhookJob($order->id, 'order.refunded'));
    }

    public function subscribe($events): array
    {
        return [
            OrderCompleted::class => 'handleOrderCompleted',
            OrderPending::class => 'handleOrderPending',
            OrderRefunded::class => 'handleOrderRefunded',
        ];
    }

    private function shouldDispatchSyncForCompleted(): bool
    {
        if (config('queue.default') === 'sync') {
            return true;
        }

        $heartbeat = Cache::get('queue_heartbeat');
        if (! is_string($heartbeat) || $heartbeat === '') {
            return true;
        }

        try {
            $last = \Illuminate\Support\Carbon::parse($heartbeat);
            if ($last->lt(now()->subMinutes(3))) {
                return true;
            }
        } catch (\Throwable) {
            return true;
        }

        try {
            $queueName = (string) config('queue.webhooks_queue', 'webhooks');
            $connection = (string) config('queue.connections.redis.connection', 'default');
            $size = (int) Redis::connection($connection)->llen("queues:{$queueName}");

            return $size >= 50;
        } catch (\Throwable) {
            return false;
        }
    }
}
