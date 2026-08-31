<?php

namespace Database\Seeders;

use App\Alumnos\Models\Enums\Nivel;
use App\Boletines\Services\GestorDePlantillas;
use App\Core\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Mueve los 7 PDF escaneados (que llegaron sueltos en
 * storage/app/private/boletines/) a boletines/plantillas/, y crea una
 * plantilla activa por cada uno leyendo su estructura_campos ya generada en
 * database/seeders/data/plantillas_boletin/.
 */
class PlantillaBoletinSeeder extends Seeder
{
    private const ARCHIVOS_ORIGEN = [
        'primaria' => 'Boletín modificado 2026.pdf',
        'secundaria_1' => 'secu_1er.pdf',
        'secundaria_2' => 'secu_2do.pdf',
        'secundaria_3' => 'secu_3ero.pdf',
        'secundaria_4' => 'secu_4to.pdf',
        'secundaria_5' => 'secu_5to.pdf',
        'secundaria_6' => 'secu_6to.pdf',
    ];

    public function run(GestorDePlantillas $gestor): void
    {
        $creadoPor = User::first();
        $disco = Storage::disk('local');

        foreach (self::ARCHIVOS_ORIGEN as $clave => $archivoOriginal) {
            $rutaOrigen = "boletines/{$archivoOriginal}";
            $rutaDestino = $this->esPrimaria($clave)
                ? 'boletines/plantillas/primaria.pdf'
                : 'boletines/plantillas/secundaria-'.$this->anioDesdeClave($clave).'.pdf';

            if ($disco->exists($rutaOrigen) && ! $disco->exists($rutaDestino)) {
                $disco->move($rutaOrigen, $rutaDestino);
            }

            $json = json_decode(
                file_get_contents(database_path("seeders/data/plantillas_boletin/{$clave}.json")),
                associative: true,
            );

            $gestor->crear([
                'nivel' => $this->esPrimaria($clave) ? Nivel::Primario : Nivel::Secundario,
                'anio' => $this->esPrimaria($clave) ? null : $this->anioDesdeClave($clave),
                'nombre' => $this->esPrimaria($clave) ? 'Boletín primaria' : 'Boletín secundaria '.$this->anioDesdeClave($clave).'º año',
                'archivo' => $rutaDestino,
                'estructura_campos' => ['secciones' => $json['secciones']],
                'creado_por_id' => $creadoPor->id,
            ]);
        }
    }

    private function esPrimaria(string $clave): bool
    {
        return $clave === 'primaria';
    }

    private function anioDesdeClave(string $clave): int
    {
        return (int) Str::after($clave, 'secundaria_');
    }
}
