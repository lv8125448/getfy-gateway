<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('checkout_slug', 16)->nullable()->unique()->after('slug');
            $table->json('checkout_config')->nullable()->after('checkout_slug');
        });

        $this->backfillCheckoutSlugs();
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['checkout_slug', 'checkout_config']);
        });
    }

    private function backfillCheckoutSlugs(): void
    {
        $products = \Illuminate\Support\Facades\DB::table('products')->whereNull('checkout_slug')->get();
        $used = \Illuminate\Support\Facades\DB::table('products')->whereNotNull('checkout_slug')->pluck('checkout_slug')->flip();

        foreach ($products as $product) {
            do {
                $slug = Str::lower(Str::random(7));
            } while (isset($used[$slug]));

            $used[$slug] = true;
            \Illuminate\Support\Facades\DB::table('products')->where('id', $product->id)->update(['checkout_slug' => $slug]);
        }
    }
};
