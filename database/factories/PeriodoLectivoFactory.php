<?php

namespace Database\Factories;

use App\Core\Models\PeriodoLectivo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PeriodoLectivo>
 */
class PeriodoLectivoFactory extends Factory
{
    protected $model = PeriodoLectivo::class;

    public function definition(): array
    {
        $anio = fake()->unique()->numberBetween(2020, 2035);

        return [
            'nombre' => (string) $anio,
            'fecha_inicio' => "{$anio}-03-01",
            'fecha_fin' => "{$anio}-12-15",
            'descuento_hermanos_pct' => 15.00,
            'activo' => false,
        ];
    }

    public function activo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => true,
        ]);
    }
}
