<?php

namespace App\Core\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Único lugar que sabe "¿este pedido vino por la red del colegio?" -- lo usa
 * RestringirAccesoPorRed para bloquear/dejar pasar, y ReciboSueldoPolicy
 * para decidir si cobrador tiene, en este pedido puntual, las mismas
 * capacidades que administrador sobre sueldos (ver ReciboSueldoPolicy).
 * Centralizado para no duplicar el chequeo de CIDR ni el criterio de "la
 * restricción está apagada" en dos lugares que podrían desincronizarse.
 */
class VerificadorDeRedEscolar
{
    public function __construct(private readonly Request $request) {}

    /**
     * Con la restricción apagada (ver config/acceso.php) se considera que
     * cualquier pedido "viene de la red del colegio" -- el concepto entero
     * de adentro/afuera queda inerte, no solo el bloqueo de acceso general.
     */
    public function estaEnLaRedDelColegio(): bool
    {
        if (! config('acceso.restriccion_de_red_habilitada')) {
            return true;
        }

        return IpUtils::checkIp($this->request->ip(), config('acceso.cidr_red_escuela'));
    }
}
