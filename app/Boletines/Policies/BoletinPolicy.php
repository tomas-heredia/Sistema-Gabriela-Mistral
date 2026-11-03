<?php

namespace App\Boletines\Policies;

use App\Boletines\Models\Boletin;
use App\Core\Models\User;

class BoletinPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador', 'director_secretario']) || $this->puedeCargarBoletines($user);
    }

    public function view(User $user, Boletin $boletin): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador', 'director_secretario']) || $this->puedeCargarBoletines($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function update(User $user, Boletin $boletin): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador', 'director_secretario']) || $this->puedeCargarBoletines($user);
    }

    public function delete(User $user, Boletin $boletin): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador', 'director_secretario']);
    }

    /**
     * Corregir un trimestre ya enviado es más sensible que cargarlo antes
     * del envío (ya le llegó un mail al tutor con la versión anterior) --
     * a diferencia de `update`, no lo tiene el cobrador ni el profesor con
     * `cargar_boletines`. Solo administrador y director_secretario.
     */
    public function corregir(User $user, Boletin $boletin): bool
    {
        return $user->hasAnyRole(['administrador', 'director_secretario']);
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
