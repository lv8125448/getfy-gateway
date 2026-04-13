<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Remove tabelas do antigo modo cloud (SaaS multi-tenant), se existirem.
 * Seguro rodar mesmo em instalações que nunca usaram cloud.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }

    public function down(): void
    {
        // Não recriar; o código cloud foi removido do projeto.
    }
};
