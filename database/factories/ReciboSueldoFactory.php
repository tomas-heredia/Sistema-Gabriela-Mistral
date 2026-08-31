<?php

namespace Database\Factories;

use App\Core\Models\User;
use App\Sueldos\Models\ReciboSueldo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReciboSueldo>
 */
class ReciboSueldoFactory extends Factory
{
    protected $model = ReciboSueldo::class;

    public function definition(): array
    {
        return [
            'profesor_id' => User::factory(),
            'periodo' => now()->format('Y-m'),
            'archivo' => 'recibos-sueldo/'.fake()->uuid().'.pdf',
            'cargado_por_id' => User::factory(),
            'fecha_carga' => now(),
        ];
    }
}
