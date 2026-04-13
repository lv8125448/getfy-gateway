<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function manageMerchantForPlatform(User $actor, User $merchant): bool
    {
        return $actor->canAccessPlatformPanel()
            && $merchant->role === User::ROLE_INFOPRODUTOR;
    }
}
