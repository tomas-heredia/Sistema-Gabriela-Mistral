<?php

namespace App\Sueldos\Policies;

use App\Core\Models\User;
use App\Core\Services\VerificadorDeRedEscolar;
use App\Sueldos\Models\ReciboSueldo;

class ReciboSueldoPolicy
{
    public function __construct(private readonly VerificadorDeRedEscolar $verificador) {}

    /**
     * Profesor y cobrador pueden listar, cada uno ve solo lo suyo -- el
     * filtro a "los propios" se aplica en la consulta, no acá. Cobrador,
     * además, ve el listado completo (como administrador) mientras está en
     * la red del colegio -- ver gestionar().
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrador', 'profesor', 'cobrador']);
    }

    public function view(User $user, ReciboSueldo $reciboSueldo): bool
    {
        if ($this->gestionar($user)) {
            return true;
        }

        return $user->hasAnyRole(['profesor', 'cobrador']) && $reciboSueldo->profesor_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->gestionar($user);
    }

    public function update(User $user, ReciboSueldo $reciboSueldo): bool
    {
        return $user->hasRole('administrador');
    }

    public function delete(User $user, ReciboSueldo $reciboSueldo): bool
    {
        return $user->hasRole('administrador');
    }

    /**
     * "Gestionar" = puede cargar un recibo para cualquier empleado y ve el
     * listado completo, no solo el propio. Administrador siempre. Cobrador
     * solo mientras el pedido viene de la red del colegio -- apenas sale de
     * esa red (o apaga el Tailscale) vuelve a solo-ver-lo-propio, sin poder
     * cargar ni ver recibos ajenos (ver ReciboSueldoTest y
     * RestringirAccesoPorRedTest para los casos cruzados).
     */
    public function gestionar(User $user): bool
    {
        return $user->hasRole('administrador')
            || ($user->hasRole('cobrador') && $this->verificador->estaEnLaRedDelColegio());
    }
}
