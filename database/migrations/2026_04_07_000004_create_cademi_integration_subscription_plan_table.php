<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Se uma tentativa anterior falhou no FK (nome muito longo no MySQL),
        // a tabela pode ter sido criada sem constraints. Recria para garantir consistência.
        Schema::dropIfExists('cademi_integration_subscription_plan');

        Schema::create('cademi_integration_subscription_plan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cademi_integration_id');
            $table->unsignedBigInteger('subscription_plan_id');
            $table->unsignedBigInteger('cademi_tag_id')->nullable();
            $table->unsignedBigInteger('cademi_produto_id')->nullable();
            $table->timestamps();

            $table->unique(['cademi_integration_id', 'subscription_plan_id'], 'cademi_int_plan_unique');

            // Nomes curtos para evitar limite de 64 chars do MySQL
            $table->foreign('cademi_integration_id', 'cad_int_plan_int_fk')
                ->references('id')
                ->on('cademi_integrations')
                ->cascadeOnDelete();
            $table->foreign('subscription_plan_id', 'cad_int_plan_plan_fk')
                ->references('id')
                ->on('subscription_plans')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cademi_integration_subscription_plan');
    }
};

