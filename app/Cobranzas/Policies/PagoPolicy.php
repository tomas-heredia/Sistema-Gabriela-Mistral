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
     * El cobrador también puede anular (a pedido del cliente) -- la
     * anulación de todas formas queda auditada con quién la hizo y el
     * motivo (ver Pago::anular()), así que la responsabilidad sigue
     * siendo clara aunque sea la misma persona que cargó el error.
     */
    public function anular(User $user, Pago $pago): bool
    {
        return $user->hasAnyRole(['administrador', 'cobrador']);
    }
}
