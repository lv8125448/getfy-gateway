<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('white_label_settings') || ! Schema::hasTable('branding_settings')) {
            return;
        }

        $now = now();
        $rows = DB::table('white_label_settings')->get();
        foreach ($rows as $row) {
            $tenantId = $row->tenant_id;
            $exists = DB::table('branding_settings')
                ->where(function ($q) use ($tenantId) {
                    if ($tenantId === null) {
                        $q->whereNull('tenant_id');
                    } else {
                        $q->where('tenant_id', $tenantId);
                    }
                })
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('branding_settings')->insert([
                'tenant_id' => $tenantId,
                'data' => $row->data,
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ]);
        }
    }

    public function down(): void
    {
        // Sem rollback de dados migrados.
    }
};
