<?php

namespace Database\Factories;

use App\Alumnos\Models\Enums\Nivel;
use App\Boletines\Models\PlantillaBoletin;
use App\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlantillaBoletin>
 */
class PlantillaBoletinFactory extends Factory
{
    protected $model = PlantillaBoletin::class;

    public function definition(): array
    {
        return [
            'nivel' => Nivel::Primario,
            'anio' => null,
            'nombre' => 'Boletín primaria',
            'archivo' => 'boletines/plantillas/primaria.pdf',
            'estructura_campos' => [
                'secciones' => [
                    [
                        'id' => 'espacios_curriculares',
                        'titulo' => 'Espacios Curriculares',
                        'tipo' => 'tabla_materias',
                    ],
                    [
                        'id' => 'promedio_anual',
                        'titulo' => 'Promedio Anual',
                        'tipo' => 'campo_numero',
                    ],
                ],
            ],
            'version' => 1,
            'reemplaza_a_id' => null,
            'activo' => true,
            'creado_por_id' => User::factory(),
        ];
    }

    public function secundaria(int $anio = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'nivel' => Nivel::Secundario,
            'anio' => $anio,
            'nombre' => "Boletín secundaria {$anio}º año",
            'archivo' => "boletines/plantillas/secundaria-{$anio}.pdf",
        ]);
    }
}
