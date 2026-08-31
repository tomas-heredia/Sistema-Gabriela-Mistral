<?php

namespace Database\Factories;

use App\Alumnos\Models\Alumno;
use App\Boletines\Models\Boletin;
use App\Boletines\Models\Enums\EstadoBoletin;
use App\Boletines\Models\PlantillaBoletin;
use App\Core\Models\PeriodoLectivo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Boletin>
 */
class BoletinFactory extends Factory
{
    protected $model = Boletin::class;

    public function definition(): array
    {
        return [
            'alumno_id' => Alumno::factory(),
            'plantilla_id' => PlantillaBoletin::factory(),
            'periodo_lectivo_id' => PeriodoLectivo::factory(),
            'estado' => EstadoBoletin::EnCurso,
        ];
    }
}
