<?php

namespace App\Alumnos\Policies;

use App\Alumnos\Models\Contrato;
use App\Core\Models\User;

class ContratoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function view(User $user, Contrato $contrato): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function update(User $user, Contrato $contrato): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function delete(User $user, Contrato $contrato): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }
}
