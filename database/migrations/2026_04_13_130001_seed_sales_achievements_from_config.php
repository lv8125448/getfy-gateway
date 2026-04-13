<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_achievements')) {
            return;
        }

        $hasAny = DB::table('sales_achievements')->exists();
        if ($hasAny) {
            return;
        }

        $configAchievements = config('conquistas.achievements', []);
        $now = now();
        foreach ($configAchievements as $index => $achievement) {
            $slug = trim((string) ($achievement['slug'] ?? ''));
            $name = trim((string) ($achievement['name'] ?? ''));
            if ($slug === '' || $name === '') {
                continue;
            }
            DB::table('sales_achievements')->insert([
                'slug' => $slug,
                'name' => $name,
                'threshold' => (float) ($achievement['threshold'] ?? 0),
                'image' => ! empty($achievement['image']) ? (string) $achievement['image'] : null,
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Não remove dados seedados automaticamente.
    }
};
