<?php

namespace App\Mora\Console\Commands;

use App\Mora\Jobs\GenerarYEnviarResumenDeDeuda;
use App\Mora\Services\CalculadorDeDeuda;
use Illuminate\Console\Command;

/**
 * Encola un resumen de deuda en PDF para cada tutor con cuotas vencidas,
 * una vez al mes. Se programa para correr a diario (ver routes/console.php)
 * pero solo hace algo a partir del día 10 -- así, si el scheduler estuvo
 * caído justo ese día, el mes no se pierde: corre apenas vuelve a levantar,
 * en vez de esperar al mes siguiente. GenerarYEnviarResumenDeDeuda es quien
 * evita que un tutor reciba dos veces el mismo mes.
 */
class GenerarResumenesMensualesDeDeuda extends Command
{
    protected $signature = 'mora:generar-resumenes';

    protected $description = 'Encola los resumenes mensuales de deuda en PDF para los tutores en mora';

    private const DIA_DEL_MES = 10;

    public function handle(CalculadorDeDeuda $calculador): int
    {
        if (today()->day < self::DIA_DEL_MES) {
            return self::SUCCESS;
        }

        $periodo = today()->format('Y-m');

        foreach ($calculador->tutoresEnMora() as $fila) {
            GenerarYEnviarResumenDeDeuda::dispatch($fila['tutor'], $periodo);
        }

        return self::SUCCESS;
    }
}
