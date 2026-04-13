<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Expressões SQL portáveis entre MySQL/MariaDB, PostgreSQL e SQLite.
 */
class SqlDialect
{
    public static function hourExpression(string $column = 'created_at'): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "EXTRACT(HOUR FROM {$column})::int",
            'sqlite' => "CAST(strftime('%H', {$column}) AS INTEGER)",
            default => "HOUR({$column})",
        };
    }

    public static function dateExpression(string $column = 'created_at'): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "({$column})::date",
            'sqlite' => "date({$column})",
            default => "DATE({$column})",
        };
    }
}
