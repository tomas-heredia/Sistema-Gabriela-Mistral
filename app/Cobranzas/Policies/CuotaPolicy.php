<?php

namespace App\Cobranzas\Policies;

use App\Cobranzas\Models\Cuota;
use App\Core\Models\User;

class CuotaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function view(User $user, Cuota $cuota): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function update(User $user, Cuota $cuota): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function delete(User $user, Cuota $cuota): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    /**
     * Anular toda la deuda vencida de un tutor desde la pantalla de Mora --
     * a diferencia del resto de las acciones sobre cuotas, solo
     * administrador (pedido explícito del cliente).
     */
    public function anularDeuda(User $user): bool
    {
        return $user->hasRole('administrador');
    }
}
