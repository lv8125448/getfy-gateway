<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('subject');
            $table->longText('body_html');
            $table->json('filter_config')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedInteger('total_recipients')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('email_campaign_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['email_campaign_id', 'email']);
            $table->index('email_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaign_sends');
        Schema::dropIfExists('email_campaigns');
    }
};
