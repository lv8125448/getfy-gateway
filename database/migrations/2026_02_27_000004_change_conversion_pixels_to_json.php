<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('conversion_pixels_new')->nullable()->after('conversion_pixels');
        });

        $products = DB::table('products')->get();
        foreach ($products as $product) {
            $value = $product->conversion_pixels;
            $jsonValue = null;
            if ($value !== null && trim((string) $value) !== '') {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $jsonValue = json_encode($decoded);
                } else {
                    $migrated = [
                        'custom_script' => [
                            ['id' => 'legacy-' . uniqid(), 'name' => 'Script legado', 'script' => $value],
                        ],
                    ];
                    $jsonValue = json_encode($migrated);
                }
            }
            DB::table('products')->where('id', $product->id)->update(['conversion_pixels_new' => $jsonValue]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('conversion_pixels');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('conversion_pixels_new', 'conversion_pixels');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('conversion_pixels_old')->nullable()->after('conversion_pixels');
        });

        $products = DB::table('products')->get();
        foreach ($products as $product) {
            $val = $product->conversion_pixels;
            $textVal = null;
            if ($val !== null) {
                $arr = json_decode($val, true);
                if (is_array($arr) && ! empty($arr['custom_script']) && is_array($arr['custom_script'])) {
                    $textVal = $arr['custom_script'][0]['script'] ?? $val;
                } else {
                    $textVal = $val;
                }
            }
            DB::table('products')->where('id', $product->id)->update(['conversion_pixels_old' => $textVal]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('conversion_pixels');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('conversion_pixels_old', 'conversion_pixels');
        });
    }
};
