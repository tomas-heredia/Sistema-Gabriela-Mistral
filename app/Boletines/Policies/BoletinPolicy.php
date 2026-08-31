<?php

namespace App\Boletines\Policies;

use App\Boletines\Models\Boletin;
use App\Core\Models\User;

class BoletinPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function view(User $user, Boletin $boletin): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function update(User $user, Boletin $boletin): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function delete(User $user, Boletin $boletin): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }
}
