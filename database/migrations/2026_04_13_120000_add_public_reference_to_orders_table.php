<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('public_reference', 16)->nullable()->unique()->after('id');
        });

        DB::table('orders')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                if ($row->public_reference !== null && $row->public_reference !== '') {
                    continue;
                }
                do {
                    $ref = strtoupper(Str::random(10));
                } while (DB::table('orders')->where('public_reference', $ref)->exists());
                DB::table('orders')->where('id', $row->id)->update(['public_reference' => $ref]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['public_reference']);
            $table->dropColumn('public_reference');
        });
    }
};
