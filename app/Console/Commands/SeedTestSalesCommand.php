<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;

class SeedTestSalesCommand extends Command
{
    protected $signature = 'conquistas:seed-test-sales 
                            {amount=10000 : Valor em reais para somar às vendas válidas}
                            {--tenant= : ID do tenant (opcional, usa o primeiro infoprodutor se não informado)}';

    protected $description = 'Insere uma venda de teste (gateway) para permitir testar o sistema de conquistas.';

    public function handle(): int
    {
        $amount = (float) $this->argument('amount');
        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;

        if ($tenantId === null) {
            $infoprodutor = User::whereIn('role', ['admin', 'infoprodutor'])
                ->orderBy('id')
                ->first();
            if (! $infoprodutor) {
                $this->error('Nenhum usuário admin/infoprodutor encontrado.');
                return self::FAILURE;
            }
            $tenantId = $infoprodutor->tenant_id;
        }

        $product = Product::forTenant($tenantId)->first();
        if (! $product) {
            $this->error("Nenhum produto encontrado para o tenant {$tenantId}. Crie um produto antes.");
            return self::FAILURE;
        }

        $buyer = User::where('tenant_id', $tenantId)->orWhereNull('tenant_id')->first();
        if (! $buyer) {
            $buyer = User::first();
        }
        if (! $buyer) {
            $this->error('Nenhum usuário encontrado para vincular como comprador.');
            return self::FAILURE;
        }

        Order::create([
            'tenant_id' => $tenantId,
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'status' => 'completed',
            'amount' => $amount,
            'email' => $buyer->email ?? 'teste@teste.com',
            'gateway' => 'efi',
            'gateway_id' => 'test-' . uniqid(),
            'approved_manually' => false,
        ]);

        $this->info("Venda de teste inserida: R$ " . number_format($amount, 2, ',', '.') . " para o tenant {$tenantId}.");
        $this->line('Acesse /conquistas para ver o progresso.');

        return self::SUCCESS;
    }
}
