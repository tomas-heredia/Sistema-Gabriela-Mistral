<?php

namespace Database\Factories;

use App\Alumnos\Models\Alumno;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\DescuentoTipo;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Enums\TipoCuota;
use App\Core\Models\PeriodoLectivo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cuota>
 */
class CuotaFactory extends Factory
{
    protected $model = Cuota::class;

    public function definition(): array
    {
        $montoBase = fake()->numberBetween(50_000, 200_000);

        return [
            'alumno_id' => Alumno::factory(),
            'periodo_lectivo_id' => PeriodoLectivo::factory(),
            'tipo' => TipoCuota::Mensualidad,
            'mes' => fake()->numberBetween(3, 12),
            'monto_base' => $montoBase,
            'descuento_tipo' => DescuentoTipo::Ninguno,
            'descuento_monto' => 0,
            'monto' => $montoBase,
            'fecha_vencimiento' => fake()->dateTimeBetween('-6 months', '+6 months'),
            'estado' => EstadoCuota::Pendiente,
        ];
    }

    public function matricula(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => TipoCuota::Matricula, 'mes' => 0]);
    }

    public function vencida(): static
    {
        return $this->state(fn (array $attributes) => ['fecha_vencimiento' => now()->subMonth()]);
    }
}
