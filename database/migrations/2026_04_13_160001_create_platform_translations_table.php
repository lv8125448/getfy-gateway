<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_translations', function (Blueprint $table) {
            $table->id();
            $table->string('group', 60);
            $table->string('key', 190);
            $table->string('locale', 20);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['group', 'key', 'locale'], 'platform_translations_group_key_locale_unique');
            $table->index(['group', 'locale'], 'platform_translations_group_locale_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_translations');
    }
};
