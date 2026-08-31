<?php

namespace Database\Factories;

use App\Alumnos\Models\Tutor;
use App\Mora\Models\Enums\EstadoNotificacion;
use App\Mora\Models\NotificacionMora;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificacionMora>
 */
class NotificacionMoraFactory extends Factory
{
    protected $model = NotificacionMora::class;

    public function definition(): array
    {
        return [
            'tutor_id' => Tutor::factory(),
            'monto_adeudado' => fake()->numberBetween(50_000, 300_000),
            'meses_adeudados' => fake()->numberBetween(1, 4),
            'estado' => EstadoNotificacion::Pendiente,
            'fecha' => today(),
        ];
    }
}
