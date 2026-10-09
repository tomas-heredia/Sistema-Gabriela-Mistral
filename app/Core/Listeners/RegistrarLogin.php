<?php

namespace App\Core\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

class RegistrarLogin
{
    public function handle(Login $event): void
    {
        Log::channel('actividad')->info('Inició sesión', [
            'usuario_id' => $event->user->id,
            'usuario' => $event->user->name,
            'ip' => request()->ip(),
        ]);
    }
}
