<?php

namespace Database\Factories;

use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Pago;
use App\Cobranzas\Models\PagoCuota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PagoCuota>
 */
class PagoCuotaFactory extends Factory
{
    protected $model = PagoCuota::class;

    public function definition(): array
    {
        return [
            'pago_id' => Pago::factory(),
            'cuota_id' => Cuota::factory(),
            'monto_aplicado' => fake()->numberBetween(10_000, 100_000),
        ];
    }
}
