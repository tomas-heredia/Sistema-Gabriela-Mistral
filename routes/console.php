<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// El intervalo real de "cada 10 días" lo garantiza la lógica del comando,
// no este cron — correr a diario lo hace robusto a que el scheduler se
// caiga un día. Ver App\Mora\Console\Commands\GenerarAvisosMora.
Schedule::command('mora:generar-avisos')->daily();
