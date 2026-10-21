<?php

namespace Database\Factories;

use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\MovimientoSaldoAFavor;
use App\Cobranzas\Models\Pago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovimientoSaldoAFavor>
 */
class MovimientoSaldoAFavorFactory extends Factory
{
    protected $model = MovimientoSaldoAFavor::class;

    public function definition(): array
    {
        return [
            'tutor_id' => Tutor::factory(),
            'pago_id' => Pago::factory(),
            'monto' => fake()->numberBetween(1_000, 50_000),
        ];
    }
}
