<?php

namespace Database\Factories;

use App\Alumnos\Livewire\Alumnos\Formulario;
use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Enums\Nivel;
use App\Alumnos\Models\Enums\Turno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alumno>
 */
class AlumnoFactory extends Factory
{
    protected $model = Alumno::class;

    public function definition(): array
    {
        $nivel = fake()->randomElement(Nivel::cases());

        if ($nivel === Nivel::Secundario) {
            $anioSecundaria = fake()->numberBetween(1, 6);
            $grado = "{$anioSecundaria}º año";
        } else {
            $anioSecundaria = null;
            $grado = fake()->randomElement(Formulario::GRADOS_PRIMARIO);
        }

        return [
            'nombre' => fake()->name(),
            'dni' => fake()->unique()->numerify('########'),
            'fecha_nacimiento' => fake()->dateTimeBetween('-18 years', '-4 years'),
            'nivel' => $nivel,
            'grado' => $grado,
            'anio_secundaria' => $anioSecundaria,
            'division' => fake()->randomElement(['A', 'B']),
            'libro_folio' => fake()->numerify('###/##'),
            'turno' => fake()->randomElement(Turno::cases()),
            'activo' => true,
        ];
    }

    public function secundario(): static
    {
        return $this->state(function (array $attributes) {
            $anioSecundaria = fake()->numberBetween(1, 6);

            return [
                'nivel' => Nivel::Secundario,
                'grado' => "{$anioSecundaria}º año",
                'anio_secundaria' => $anioSecundaria,
            ];
        });
    }

    public function primario(): static
    {
        return $this->state(fn (array $attributes) => [
            'nivel' => Nivel::Primario,
            'grado' => fake()->randomElement(Formulario::GRADOS_PRIMARIO),
            'anio_secundaria' => null,
        ]);
    }
}
