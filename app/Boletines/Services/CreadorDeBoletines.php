<?php

namespace App\Boletines\Services;

use App\Alumnos\Models\Alumno;
use App\Boletines\Exceptions\PlantillaNoEncontradaException;
use App\Boletines\Models\Boletin;
use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Models\PlantillaBoletin;
use App\Core\Models\PeriodoLectivo;

/**
 * Crea el boletín de un alumno para un período con sus 3 trimestres
 * regulares vacíos (pendiente) de una sola vez — mismo patrón que
 * GeneradorDeCuotas con las cuotas del año. La plantilla se elige una sola
 * vez acá y queda fija: no se re-evalúa después, aunque cambie la activa.
 */
class CreadorDeBoletines
{
    public function crear(Alumno $alumno, PeriodoLectivo $periodo): Boletin
    {
        $plantilla = $this->plantillaParaAlumno($alumno);

        $boletin = Boletin::create([
            'alumno_id' => $alumno->id,
            'plantilla_id' => $plantilla->id,
            'periodo_lectivo_id' => $periodo->id,
        ]);

        foreach ([1, 2, 3] as $trimestre) {
            BoletinTrimestre::create([
                'boletin_id' => $boletin->id,
                'trimestre' => $trimestre,
            ]);
        }

        return $boletin;
    }

    private function plantillaParaAlumno(Alumno $alumno): PlantillaBoletin
    {
        $plantilla = PlantillaBoletin::query()
            ->where('nivel', $alumno->nivel)
            ->where('anio', $alumno->anio_secundaria)
            ->where('activo', true)
            ->first();

        if (! $plantilla) {
            throw new PlantillaNoEncontradaException(
                "No hay una plantilla de libreta activa para nivel={$alumno->nivel->value}, año={$alumno->anio_secundaria}."
            );
        }

        return $plantilla;
    }
}
