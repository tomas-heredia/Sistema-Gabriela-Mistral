<?php

namespace App\Mora\Policies;

use App\Core\Models\User;
use App\Mora\Models\NotificacionMora;

class NotificacionMoraPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function view(User $user, NotificacionMora $notificacionMora): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function update(User $user, NotificacionMora $notificacionMora): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function delete(User $user, NotificacionMora $notificacionMora): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }
}
