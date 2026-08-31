<?php

namespace App\Sueldos\Policies;

use App\Core\Models\User;
use App\Sueldos\Models\ReciboSueldo;

class ReciboSueldoPolicy
{
    /**
     * El profesor puede listar — el filtro a "los propios" se aplica en la
     * consulta, no acá. Cobrador sin ningún acceso (regla de CLAUDE.md).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'profesor']);
    }

    public function view(User $user, ReciboSueldo $reciboSueldo): bool
    {
        if ($user->hasRole('administrador')) {
            return true;
        }

        return $user->hasRole('profesor') && $reciboSueldo->profesor_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('administrador');
    }

    public function update(User $user, ReciboSueldo $reciboSueldo): bool
    {
        return $user->hasRole('administrador');
    }

    public function delete(User $user, ReciboSueldo $reciboSueldo): bool
    {
        return $user->hasRole('administrador');
    }
}
