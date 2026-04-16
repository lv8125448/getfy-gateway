<?php

namespace App\Services;

use App\Models\User;

class BuyerAccountService
{
    /**
     * Garante utilizador de compra por e-mail: conta global (tenant_id null) e papel cliente
     * quando aplicável. Não altera papel de infoprodutor ou equipe.
     *
     * @return array{user: User, was_recently_created: bool}
     */
    public function ensureBuyerFromCheckout(
        string $email,
        string $name,
        string $passwordHash,
        bool $isMemberAreaProduct,
    ): array {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name !== '' ? $name : $email,
                'password' => $passwordHash,
                'role' => User::ROLE_CLIENTE,
                'tenant_id' => null,
            ]
        );

        $wasRecentlyCreated = $user->wasRecentlyCreated;

        if ($user->isCliente()) {
            $user->update(['tenant_id' => null]);
            if ($isMemberAreaProduct && ! $wasRecentlyCreated) {
                $user->update(['password' => $passwordHash]);
            }
        }

        return ['user' => $user->fresh(), 'was_recently_created' => $wasRecentlyCreated];
    }
}
