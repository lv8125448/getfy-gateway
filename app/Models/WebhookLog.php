<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    protected $fillable = [
        'webhook_id',
        'event',
        'event_label',
        'request_payload',
        'response_status',
        'response_body',
        'success',
        'error_message',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'success' => 'boolean',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
