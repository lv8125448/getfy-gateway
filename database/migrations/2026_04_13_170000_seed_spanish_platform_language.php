<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_languages') || ! Schema::hasTable('platform_translations')) {
            return;
        }

        $locale = 'es';
        $group = (string) config('panel_i18n.group', 'seller');
        $messages = (array) config("panel_i18n.locales.$locale", []);
        $now = now();

        $lang = DB::table('platform_languages')->where('code', $locale)->first();
        if (! $lang) {
            $maxSort = (int) DB::table('platform_languages')->max('sort_order');
            DB::table('platform_languages')->insert([
                'code' => $locale,
                'name' => 'Español',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => $maxSort + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($messages as $key => $value) {
            DB::table('platform_translations')->updateOrInsert(
                [
                    'group' => $group,
                    'key' => (string) $key,
                    'locale' => $locale,
                ],
                [
                    'value' => (string) $value,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        // Keep seeded language/translations.
    }
};

