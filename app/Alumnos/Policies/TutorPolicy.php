<?php

namespace App\Alumnos\Policies;

use App\Alumnos\Models\Tutor;
use App\Core\Models\User;

class TutorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function view(User $user, Tutor $tutor): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function update(User $user, Tutor $tutor): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function delete(User $user, Tutor $tutor): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }
}
