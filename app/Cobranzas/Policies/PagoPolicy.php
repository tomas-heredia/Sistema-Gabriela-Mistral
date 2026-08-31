<?php

namespace App\Cobranzas\Policies;

use App\Cobranzas\Models\Pago;
use App\Core\Models\User;

class PagoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function view(User $user, Pago $pago): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function update(User $user, Pago $pago): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    public function delete(User $user, Pago $pago): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }

    /**
     * El cobrador carga pagos, pero no puede anularlos — solo administrador,
     * para evitar que quien puede haber cometido el error también lo borre
     * de la vista sin dejar rastro claro de responsabilidad.
     */
    public function anular(User $user, Pago $pago): bool
    {
        return $user->hasRole('administrador');
    }
}
