<?php

namespace App\Cobranzas\Policies;

use App\Cobranzas\Models\Beca;
use App\Core\Models\User;

class BecaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function view(User $user, Beca $beca): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function update(User $user, Beca $beca): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function delete(User $user, Beca $beca): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }
}
