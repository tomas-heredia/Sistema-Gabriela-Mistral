<?php

namespace App\Core\Policies;

use App\Core\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('administrador');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRole('administrador');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('administrador');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRole('administrador');
    }

    /**
     * Un administrador no puede eliminarse a sí mismo desde esta pantalla
     * -- evita que se saque el acceso por accidente.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->hasRole('administrador') && $user->isNot($model);
    }
}
