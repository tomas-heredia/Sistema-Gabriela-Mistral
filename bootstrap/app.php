<?php

use App\Core\Http\Middleware\RestringirAccesoPorRed;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Los comandos Artisan de dominio viven junto a su módulo
    // (App\Mora\Console\Commands), no en app/Console/Commands.
    ->withCommands([
        __DIR__.'/../app/Mora/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // "tailscale serve" reenvía el tráfico de la VPN hacia la app por
        // loopback (ver RestringirAccesoPorRed) -- sin esto, $request->ip()
        // vería siempre la IP del proxy en vez de la del dispositivo
        // conectado por Tailscale. 172.16.1.0/24 (no solo 127.0.0.1) porque
        // Docker Swarm, con el puerto en modo host y el servicio también en
        // una red overlay, reescribe hasta el tráfico por loopback a la IP
        // del gateway interno (docker_gwbridge) antes de que llegue al
        // contenedor -- ese rango es interno de Docker, nunca alcanzable
        // desde afuera, así que confiar en él es seguro.
        $middleware->trustProxies(
            at: ['127.0.0.1', '172.16.1.0/24'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->web(append: [
            RestringirAccesoPorRed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
