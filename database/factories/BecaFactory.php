<?php

namespace Database\Factories;

use App\Alumnos\Models\Alumno;
use App\Cobranzas\Models\Beca;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Beca>
 */
class BecaFactory extends Factory
{
    protected $model = Beca::class;

    public function definition(): array
    {
        return [
            'alumno_id' => Alumno::factory(),
            'periodo_lectivo_id' => PeriodoLectivo::factory(),
            'motivo' => fake()->randomElement(['Beca socioeconómica', 'Beca deportiva', 'Beca convenio institucional']),
            'aprobado_por_id' => User::factory(),
            'fecha_otorgamiento' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
