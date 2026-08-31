<?php

namespace App\Alumnos\Models;

use App\Alumnos\Models\Enums\Nivel;
use App\Alumnos\Models\Enums\Turno;
use Database\Factories\AlumnoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'dni', 'fecha_nacimiento', 'nivel', 'grado', 'anio_secundaria', 'division', 'libro_folio', 'turno', 'activo'])]
class Alumno extends Model
{
    use HasFactory;

    /**
     * Explícito por convención del proyecto: no depender de la
     * pluralización automática de Eloquent (asume inglés) con nombres
     * de tabla en español.
     */
    protected $table = 'alumnos';

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'nivel' => Nivel::class,
            'turno' => Turno::class,
            'activo' => 'boolean',
        ];
    }

    public function tutores(): BelongsToMany
    {
        return $this->belongsToMany(Tutor::class, 'alumno_tutor')
            ->withPivot('vinculo', 'responsable_pago')
            ->withTimestamps();
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    /**
     * Se declara explícito porque este modelo vive en App\Alumnos\Models,
     * fuera de App\Models donde HasFactory adivinaría la factory por convención.
     */
    protected static function newFactory(): AlumnoFactory
    {
        return AlumnoFactory::new();
    }
}
