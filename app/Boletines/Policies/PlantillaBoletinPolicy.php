<?php

namespace App\Boletines\Policies;

use App\Boletines\Models\PlantillaBoletin;
use App\Core\Models\User;

class PlantillaBoletinPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function view(User $user, PlantillaBoletin $plantillaBoletin): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('administrador');
    }

    public function update(User $user, PlantillaBoletin $plantillaBoletin): bool
    {
        return $user->hasRole('administrador');
    }

    public function delete(User $user, PlantillaBoletin $plantillaBoletin): bool
    {
        return $user->hasRole('administrador');
    }
}
