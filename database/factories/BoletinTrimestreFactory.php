<?php

namespace Database\Factories;

use App\Boletines\Models\Boletin;
use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Models\Enums\EstadoTrimestre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoletinTrimestre>
 */
class BoletinTrimestreFactory extends Factory
{
    protected $model = BoletinTrimestre::class;

    public function definition(): array
    {
        return [
            'boletin_id' => Boletin::factory(),
            'trimestre' => fake()->numberBetween(1, 3),
            'datos' => null,
            'estado' => EstadoTrimestre::Pendiente,
        ];
    }

    public function cargado(): static
    {
        return $this->state(fn (array $attributes) => [
            'datos' => ['espacios_curriculares' => [['materia' => 'Lengua', 'nota' => 8]]],
            'estado' => EstadoTrimestre::Cargado,
        ]);
    }
}
