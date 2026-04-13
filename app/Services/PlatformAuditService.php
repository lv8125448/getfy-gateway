<?php

namespace App\Services;

use App\Models\PlatformAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PlatformAuditService
{
    public static function log(string $action, ?array $metadata = null, ?Request $request = null): void
    {
        if (! Schema::hasTable('platform_audit_logs')) {
            return;
        }

        $request = $request ?? request();
        $user = $request->user();

        PlatformAuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'metadata' => $metadata,
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }
}
