<?php

namespace App\Core\Listeners;

use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

class RegistrarLogout
{
    public function handle(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        Log::channel('actividad')->info('Cerró sesión', [
            'usuario_id' => $event->user->id,
            'usuario' => $event->user->name,
            'ip' => request()->ip(),
        ]);
    }
}
