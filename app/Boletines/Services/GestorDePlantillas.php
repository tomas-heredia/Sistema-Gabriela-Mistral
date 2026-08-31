<?php

namespace App\Boletines\Services;

use App\Alumnos\Models\Enums\Nivel;
use App\Boletines\Exceptions\PlantillaActivaExistenteException;
use App\Boletines\Models\PlantillaBoletin;

/**
 * Una plantilla no se edita in situ: subir un archivo nuevo o cambiar la
 * estructura de campos crea una fila nueva y desactiva la anterior. La
 * unicidad "solo una activa por (nivel, año)" es de aplicación, no de base
 * de datos — MySQL no soporta índice único parcial (ver CLAUDE.md).
 */
class GestorDePlantillas
{
    public function crear(array $datos): PlantillaBoletin
    {
        $this->asegurarSinActivaPara($datos['nivel'], $datos['anio'] ?? null);

        return PlantillaBoletin::create([
            ...$datos,
            'version' => 1,
            'reemplaza_a_id' => null,
            'activo' => true,
        ]);
    }

    public function nuevaVersion(PlantillaBoletin $anterior, array $datos): PlantillaBoletin
    {
        $nueva = PlantillaBoletin::create([
            'nivel' => $anterior->nivel,
            'anio' => $anterior->anio,
            'nombre' => $datos['nombre'] ?? $anterior->nombre,
            'archivo' => $datos['archivo'] ?? $anterior->archivo,
            'estructura_campos' => $datos['estructura_campos'] ?? $anterior->estructura_campos,
            'version' => $anterior->version + 1,
            'reemplaza_a_id' => $anterior->id,
            'activo' => true,
            'creado_por_id' => $datos['creado_por_id'] ?? $anterior->creado_por_id,
        ]);

        $this->desactivar($anterior);

        return $nueva;
    }

    public function desactivar(PlantillaBoletin $plantilla): void
    {
        $plantilla->update(['activo' => false]);
    }

    private function asegurarSinActivaPara(Nivel|string $nivel, ?int $anio): void
    {
        $existe = PlantillaBoletin::query()
            ->where('nivel', $nivel)
            ->where('anio', $anio)
            ->where('activo', true)
            ->exists();

        if ($existe) {
            throw new PlantillaActivaExistenteException(
                'Ya existe una plantilla activa para ese nivel/año — usá nuevaVersion() en vez de crear una segunda.'
            );
        }
    }
}
