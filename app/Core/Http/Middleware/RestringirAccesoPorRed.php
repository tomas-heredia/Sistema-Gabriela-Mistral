<?php

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Cobrador y administra_alumnos manejan dinero y datos de alumnos desde
 * dispositivos propios (celular, notebook) que se llevan fuera del
 * colegio -- el pedido del colegio es que esos dos roles solo puedan usar
 * el sistema estando conectados a su red. Como esa red usa Starlink
 * residencial (CGNAT, sin IP pública estable para hacer whitelist directo),
 * se identifica en cambio por la VPN: el VPS y cada dispositivo autorizado
 * viven en el mismo tailnet de Tailscale, que reparte IPs del rango privado
 * 100.64.0.0/10 -- un pedido que llega por ese rango vino por el túnel.
 *
 * administrador y profesor no tienen esta restricción.
 */
class RestringirAccesoPorRed
{
    private const ROLES_RESTRINGIDOS = ['cobrador', 'administra_alumnos'];

    public function handle(Request $request, Closure $next)
    {
        if (! config('acceso.restriccion_de_red_habilitada')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user?->hasAnyRole(self::ROLES_RESTRINGIDOS) && ! $this->accedeDesdeLaRedDelColegio($request)) {
            // Desloguea en vez de solo devolver 403 -- si no, un usuario
            // restringido que quedó autenticado se queda trabado viendo el
            // error en cada pantalla, sin forma de volver al login para
            // entrar con otra cuenta (ej. un administrador, sin esta
            // restricción) desde el mismo dispositivo/red.
            Auth::guard('web')->logout();
            Session::invalidate();
            Session::regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Este usuario solo puede acceder al sistema conectado a la red del colegio.');
        }

        return $next($request);
    }

    private function accedeDesdeLaRedDelColegio(Request $request): bool
    {
        return IpUtils::checkIp($request->ip(), config('acceso.cidr_red_escuela'));
    }
}
