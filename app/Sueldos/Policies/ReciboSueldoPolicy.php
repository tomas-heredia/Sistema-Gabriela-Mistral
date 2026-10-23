<?php

namespace App\Sueldos\Policies;

use App\Core\Models\User;
use App\Sueldos\Models\ReciboSueldo;

class ReciboSueldoPolicy
{
    /**
     * Profesor y cobrador pueden listar, cada uno ve solo lo suyo -- el
     * filtro a "los propios" se aplica en la consulta, no acá. A cobrador se
     * le abrió este acceso (y la excepción a la restricción de red en
     * RestringirAccesoPorRed) porque necesita ver su propio recibo de sueldo
     * desde cualquier lado, no porque pueda gestionar recibos de terceros.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'profesor', 'cobrador']);
    }

    public function view(User $user, ReciboSueldo $reciboSueldo): bool
    {
        if ($user->hasRole('administrador')) {
            return true;
        }

        return $user->hasAnyRole(['profesor', 'cobrador']) && $reciboSueldo->profesor_id === $user->id;
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
