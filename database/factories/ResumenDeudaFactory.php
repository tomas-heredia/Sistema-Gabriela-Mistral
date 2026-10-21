<?php

namespace Database\Factories;

use App\Alumnos\Models\Tutor;
use App\Mora\Models\ResumenDeuda;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResumenDeuda>
 */
class ResumenDeudaFactory extends Factory
{
    protected $model = ResumenDeuda::class;

    public function definition(): array
    {
        return [
            'tutor_id' => Tutor::factory(),
            'periodo' => today()->format('Y-m'),
            'monto_adeudado' => fake()->numberBetween(50_000, 300_000),
            'pdf_path' => 'resumenes-deuda/'.fake()->uuid().'.pdf',
            'enviado_at' => now(),
        ];
    }
}
