<?php

namespace App\Core\Policies;

use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;

class PeriodoLectivoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function view(User $user, PeriodoLectivo $periodoLectivo): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('administrador');
    }

    public function update(User $user, PeriodoLectivo $periodoLectivo): bool
    {
        return $user->hasRole('administrador');
    }

    public function delete(User $user, PeriodoLectivo $periodoLectivo): bool
    {
        return $user->hasRole('administrador');
    }
}
