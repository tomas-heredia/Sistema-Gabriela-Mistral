<?php

namespace Database\Factories;

use App\Alumnos\Models\Enums\Nivel;
use App\Cobranzas\Models\Arancel;
use App\Cobranzas\Models\Enums\TipoCuota;
use App\Core\Models\PeriodoLectivo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Arancel>
 */
class ArancelFactory extends Factory
{
    protected $model = Arancel::class;

    public function definition(): array
    {
        return [
            'periodo_lectivo_id' => PeriodoLectivo::factory(),
            'nivel' => fake()->randomElement(Nivel::cases()),
            'tipo' => fake()->randomElement(TipoCuota::cases()),
            'monto' => fake()->numberBetween(50_000, 200_000),
        ];
    }

    public function matricula(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => TipoCuota::Matricula, 'monto' => 150_000]);
    }

    public function mensualidad(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => TipoCuota::Mensualidad, 'monto' => 100_000]);
    }
}
