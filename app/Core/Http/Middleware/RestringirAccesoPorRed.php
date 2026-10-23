<?php

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Cobrador maneja dinero (cobranzas, mora) desde dispositivos propios
 * (celular, notebook) que se llevan fuera del colegio -- el pedido del
 * colegio es que ese rol solo pueda usar el sistema estando conectado a su
 * red. Como esa red usa Starlink residencial (CGNAT, sin IP pública estable
 * para hacer whitelist directo), se identifica en cambio por la VPN: el VPS
 * y cada dispositivo autorizado viven en el mismo tailnet de Tailscale, que
 * reparte IPs del rango privado 100.64.0.0/10 -- un pedido que llega por ese
 * rango vino por el túnel.
 *
 * administrador y profesor no tienen esta restricción -- un profesor con
 * el permiso 'cargar_boletines' necesita poder cargar notas desde su casa,
 * no solo desde el colegio (a diferencia del extinto rol administra_alumnos,
 * que sí la tenía).
 */
class RestringirAccesoPorRed
{
    private const ROLES_RESTRINGIDOS = ['cobrador'];

    /**
     * Alias Livewire (ver AppServiceProvider::boot) de los componentes de
     * Sueldos -- cobrador puede ver su propio recibo desde cualquier red
     * (ver ReciboSueldoPolicy), así que tanto la carga de la página como
     * las siguientes peticiones AJAX a /livewire/update de ese componente
     * quedan exceptuadas acá.
     */
    private const ALIAS_LIVEWIRE_EXCEPTUADOS = ['recibos-sueldo.listado', 'recibos-sueldo.formulario'];

    public function handle(Request $request, Closure $next)
    {
        if (! config('acceso.restriccion_de_red_habilitada')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user?->hasAnyRole(self::ROLES_RESTRINGIDOS) && $this->esRutaExceptuada($request)) {
            return $next($request);
        }

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

    /**
     * La carga inicial de la página de Sueldos se identifica por nombre de
     * ruta. Pero cada click posterior (buscar, paginar) no pega a esa ruta:
     * Livewire manda TODAS las interacciones de CUALQUIER componente de la
     * app a la misma URL genérica /livewire/update (ver la nota sobre esto
     * en CLAUDE.md) -- así que ahí hay que mirar adentro del payload qué
     * componente es, en vez de confiar en la ruta. Si el pedido trae más de
     * un componente y alguno no es de Sueldos, no se exceptúa nada.
     */
    private function esRutaExceptuada(Request $request): bool
    {
        if ($request->routeIs('recibos-sueldo.*')) {
            return true;
        }

        if (! $request->routeIs('default.livewire.update')) {
            return false;
        }

        $componentes = $request->input('components', []);

        if (! is_array($componentes) || empty($componentes)) {
            return false;
        }

        foreach ($componentes as $componente) {
            $snapshot = json_decode($componente['snapshot'] ?? '', associative: true);
            $nombre = $snapshot['memo']['name'] ?? null;

            if (! in_array($nombre, self::ALIAS_LIVEWIRE_EXCEPTUADOS, true)) {
                return false;
            }
        }

        return true;
    }
}
