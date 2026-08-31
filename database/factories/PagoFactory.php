<?php

namespace Database\Factories;

use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\Enums\MedioPago;
use App\Cobranzas\Models\Pago;
use App\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
{
    protected $model = Pago::class;

    public function definition(): array
    {
        return [
            'tutor_id' => Tutor::factory(),
            'monto' => fake()->numberBetween(50_000, 200_000),
            'medio_pago' => fake()->randomElement(MedioPago::cases()),
            'fecha' => fake()->dateTimeBetween('-3 months', 'now'),
            'numero_recibo' => fake()->unique()->numerify('R-######'),
            'cobrador_id' => User::factory(),
        ];
    }

    public function anulado(): static
    {
        return $this->state(fn (array $attributes) => [
            'anulado_at' => now(),
            'anulado_por_id' => User::factory(),
            'motivo_anulacion' => 'Cargado por error',
        ]);
    }
}
