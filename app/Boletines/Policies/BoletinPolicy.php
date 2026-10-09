<?php

namespace App\Boletines\Policies;

use App\Boletines\Models\Boletin;
use App\Core\Models\User;

class BoletinPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']) || $this->puedeCargarBoletines($user);
    }

    public function view(User $user, Boletin $boletin): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']) || $this->puedeCargarBoletines($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function update(User $user, Boletin $boletin): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']) || $this->puedeCargarBoletines($user);
    }

    public function delete(User $user, Boletin $boletin): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    /**
     * Reemplaza al antiguo rol administra_alumnos: un profesor puede, además
     * de ver sus propios recibos de sueldo, cargar y enviar libretas si el
     * administrador le asignó este permiso puntual.
     */
    private function puedeCargarBoletines(User $user): bool
    {
        return $user->hasRole('profesor') && $user->hasPermissionTo('cargar_boletines');
    }
}
