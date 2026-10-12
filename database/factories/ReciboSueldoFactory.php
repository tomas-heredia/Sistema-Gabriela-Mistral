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
            'cargado_por_id' => User::factory(),
            'fecha_carga' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ReciboSueldo $recibo) {
            if ($recibo->archivos()->doesntExist()) {
                ReciboSueldoArchivoFactory::new()->create(['recibo_sueldo_id' => $recibo->id]);
            }
        });
    }
}
