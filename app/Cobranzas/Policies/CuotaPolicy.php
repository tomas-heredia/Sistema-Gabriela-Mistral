<?php

namespace App\Cobranzas\Policies;

use App\Cobranzas\Models\Cuota;
use App\Core\Models\User;

class CuotaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function view(User $user, Cuota $cuota): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function update(User $user, Cuota $cuota): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function delete(User $user, Cuota $cuota): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }
}
