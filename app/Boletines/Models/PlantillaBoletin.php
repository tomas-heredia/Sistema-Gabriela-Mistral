<?php

namespace App\Boletines\Models;

use App\Alumnos\Models\Enums\Nivel;
use App\Core\Models\User;
use Database\Factories\PlantillaBoletinFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Nunca se edita in situ: subir un archivo nuevo o cambiar la estructura de
 * campos crea una fila nueva (ver GestorDePlantillas::nuevaVersion) y
 * desactiva esta. Así un boletín ya cargado conserva el formato exacto con
 * el que se cargó, aunque el colegio cambie el diseño después.
 */
#[Fillable(['nivel', 'anio', 'nombre', 'archivo', 'estructura_campos', 'version', 'reemplaza_a_id', 'activo', 'creado_por_id'])]
class PlantillaBoletin extends Model
{
    use HasFactory;

    protected $table = 'plantillas_boletin';

    protected function casts(): array
    {
        return [
            'nivel' => Nivel::class,
            'estructura_campos' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function reemplazaA(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reemplaza_a_id');
    }

    public function versionSiguiente(): HasOne
    {
        return $this->hasOne(self::class, 'reemplaza_a_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    public function boletines(): HasMany
    {
        return $this->hasMany(Boletin::class, 'plantilla_id');
    }

    /**
     * Se declara explícito porque este modelo vive en App\Boletines\Models,
     * fuera de App\Models donde HasFactory adivinaría la factory por convención.
     */
    protected static function newFactory(): PlantillaBoletinFactory
    {
        return PlantillaBoletinFactory::new();
    }
}
