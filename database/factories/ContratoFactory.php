<?php

namespace Database\Factories;

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Contrato;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contrato>
 */
class ContratoFactory extends Factory
{
    protected $model = Contrato::class;

    public function definition(): array
    {
        return [
            'alumno_id' => Alumno::factory(),
            'periodo_lectivo_id' => PeriodoLectivo::factory(),
            'archivo' => 'contratos/'.fake()->uuid().'.pdf',
            'fecha' => fake()->dateTimeBetween('-1 year', 'now'),
            'cargado_por_id' => User::factory(),
        ];
    }
}
