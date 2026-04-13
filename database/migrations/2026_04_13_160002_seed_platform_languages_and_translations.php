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

        $now = now();
        $locales = (array) config('panel_i18n.locales', []);
        $defaultLocale = (string) config('panel_i18n.default_locale', 'pt_BR');
        $group = (string) config('panel_i18n.group', 'seller');

        foreach ($locales as $code => $messages) {
            $exists = DB::table('platform_languages')->where('code', $code)->exists();
            if (! $exists) {
                DB::table('platform_languages')->insert([
                    'code' => $code,
                    'name' => $code === 'pt_BR' ? 'Português (Brasil)' : strtoupper($code),
                    'is_active' => true,
                    'is_default' => $code === $defaultLocale,
                    'sort_order' => $code === $defaultLocale ? 1 : 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ((array) $messages as $key => $value) {
                $rowExists = DB::table('platform_translations')
                    ->where('group', $group)
                    ->where('key', $key)
                    ->where('locale', $code)
                    ->exists();
                if ($rowExists) {
                    continue;
                }
                DB::table('platform_translations')->insert([
                    'group' => $group,
                    'key' => (string) $key,
                    'locale' => $code,
                    'value' => (string) $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // No rollback for seeded i18n data.
    }
};
