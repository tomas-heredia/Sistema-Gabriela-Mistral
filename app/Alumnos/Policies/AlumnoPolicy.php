<?php

namespace App\Alumnos\Policies;

use App\Alumnos\Models\Alumno;
use App\Core\Models\User;

class AlumnoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function view(User $user, Alumno $alumno): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function update(User $user, Alumno $alumno): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function delete(User $user, Alumno $alumno): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }
}
