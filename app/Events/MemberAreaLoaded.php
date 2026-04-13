<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class MemberAreaLoaded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        /** @var Collection<int, \App\Models\Product> */
        public Collection $produtos
    ) {}
}
