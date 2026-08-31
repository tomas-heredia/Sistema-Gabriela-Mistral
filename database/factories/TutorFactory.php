<?php

namespace Database\Factories;

use App\Alumnos\Models\Tutor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tutor>
 */
class TutorFactory extends Factory
{
    protected $model = Tutor::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'dni' => fake()->unique()->numerify('########'),
            'domicilio' => fake()->address(),
            'telefono' => fake()->phoneNumber(),
            'correo' => fake()->safeEmail(),
        ];
    }
}
