<?php

namespace App\Alumnos\Models;

use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Database\Factories\ContratoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contrato extends Model
{
    use HasFactory;

    /**
     * Explícito por convención del proyecto: no depender de la
     * pluralización automática de Eloquent (asume inglés) con nombres
     * de tabla en español.
     */
    protected $table = 'contratos';

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function periodoLectivo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLectivo::class);
    }

    public function cargadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cargado_por_id');
    }

    /**
     * Se declara explícito porque este modelo vive en App\Alumnos\Models,
     * fuera de App\Models donde HasFactory adivinaría la factory por convención.
     */
    protected static function newFactory(): ContratoFactory
    {
        return ContratoFactory::new();
    }
}
