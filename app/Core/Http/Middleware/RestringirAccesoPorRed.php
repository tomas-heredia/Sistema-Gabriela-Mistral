<?php

namespace App\Core\Http\Middleware;

use App\Core\Services\VerificadorDeRedEscolar;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

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
     * Rutas de Sueldos que cobrador puede usar para ver (nunca cargar) su
     * propio recibo desde cualquier red (ver ReciboSueldoPolicy). A
     * propósito NO incluye 'recibos-sueldo.crear' -- cargar un recibo ajeno
     * sigue exigiendo estar en la red del colegio, así que esa ruta pasa
     * por el chequeo normal de más abajo.
     *
     * 'profile' no tiene ese matiz: a diferencia de sueldos, ahí no existe
     * ningún dato "ajeno" que ver -- siempre es la cuenta del propio
     * usuario logueado -- así que se exceptúa entera.
     */
    private const RUTAS_EXCEPTUADAS = ['recibos-sueldo.index', 'recibos-sueldo.descargar', 'profile'];

    /**
     * Alias Livewire (ver AppServiceProvider::boot, y el propio Volt para
     * las de perfil) de los componentes de arriba -- las peticiones AJAX a
     * /livewire/update posteriores a cargar esas páginas (buscar, paginar,
     * guardar el perfil) quedan exceptuadas igual que la carga inicial. El
     * formulario de carga de Sueldos queda afuera a propósito, mismo
     * motivo que arriba.
     */
    private const ALIAS_LIVEWIRE_EXCEPTUADOS = [
        'recibos-sueldo.listado',
        'profile.update-profile-information-form',
        'profile.update-password-form',
        'profile.delete-user-form',
    ];

    public function __construct(private readonly VerificadorDeRedEscolar $verificador) {}

    public function handle(Request $request, Closure $next)
    {
        if (! config('acceso.restriccion_de_red_habilitada')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user?->hasAnyRole(self::ROLES_RESTRINGIDOS) && $this->esRutaExceptuada($request)) {
            return $next($request);
        }

        if ($user?->hasAnyRole(self::ROLES_RESTRINGIDOS) && ! $this->verificador->estaEnLaRedDelColegio()) {
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
        if (in_array($request->route()?->getName(), self::RUTAS_EXCEPTUADAS, true)) {
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
