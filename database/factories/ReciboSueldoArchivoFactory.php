<?php

namespace Database\Factories;

use App\Sueldos\Models\ReciboSueldo;
use App\Sueldos\Models\ReciboSueldoArchivo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReciboSueldoArchivo>
 */
class ReciboSueldoArchivoFactory extends Factory
{
    protected $model = ReciboSueldoArchivo::class;

    public function definition(): array
    {
        return [
            'recibo_sueldo_id' => ReciboSueldo::factory(),
            'archivo' => 'recibos-sueldo/'.fake()->uuid().'.pdf',
        ];
    }
}
