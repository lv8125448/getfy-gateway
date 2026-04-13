<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;

class TeamAccessService
{
    /**
     * @return array<string, bool>
     */
    public function permissionsFor(User $user): array
    {
        if ($user->isAdmin() || $user->isInfoprodutor()) {
            return $this->allPermissions();
        }

        if (! $user->isTeam()) {
            return [];
        }

        $raw = $user->teamRole?->permissions;
        if (! is_array($raw)) {
            return [];
        }

        $perms = [];
        foreach ($raw as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $perms[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $perms;
    }

    public function can(User $user, string $permission): bool
    {
        if ($user->isAdmin() || $user->isInfoprodutor()) {
            return true;
        }

        if (! $user->isTeam()) {
            return false;
        }

        $perms = $this->permissionsFor($user);

        return ! empty($perms[$permission]);
    }

    /**
     * @return list<string>
     */
    public function allowedProductIdsFor(User $user): array
    {
        if ($user->isAdmin() || $user->isInfoprodutor()) {
            $tenantId = $user->tenant_id;
            if ($tenantId === null) {
                return [];
            }
            return Product::forTenant($tenantId)->pluck('id')->all();
        }

        if (! $user->isTeam()) {
            return [];
        }

        return $user->teamRole?->products()->pluck('products.id')->all() ?? [];
    }

    /**
     * @return array<string, bool>
     */
    public function allPermissions(): array
    {
        return [
            'dashboard.view' => true,
            'vendas.view' => true,
            'financeiro.view' => true,
            'produtos.view' => true,
            'relatorios.view' => true,
            'integracoes.view' => true,
            'email_marketing.view' => true,
            'api_pagamentos.view' => true,
            'configuracoes.view' => true,
            'equipe.manage' => true,
        ];
    }
}

