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
        // vería siempre 127.0.0.1 en vez de la IP real del dispositivo
        // conectado por Tailscale.
        $middleware->trustProxies(
            at: ['127.0.0.1'],
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
