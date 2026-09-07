<?php

namespace App\Core\Models;

use Database\Factories\PeriodoLectivoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'fecha_inicio', 'fecha_fin', 'descuento_hermanos_pct', 'activo'])]
class PeriodoLectivo extends Model
{
    use HasFactory;

    /**
     * Se declara explícito porque la pluralización automática de Eloquent
     * asume inglés: "PeriodoLectivo" adivinaría "periodo_lectivos", no
     * "periodos_lectivos".
     */
    protected $table = 'periodos_lectivos';

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'descuento_hermanos_pct' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * Desactiva cualquier otro período activo y activa este. Solo puede
     * haber uno activo a la vez -- es de aplicación, no de la base (MySQL
     * no soporta índice único parcial, mismo caso que PlantillaBoletin).
     */
    public function activar(): void
    {
        static::where('activo', true)->where('id', '!=', $this->id)->update(['activo' => false]);
        $this->update(['activo' => true]);
    }

    /**
     * Se declara explícito porque este modelo vive en App\Core\Models,
     * fuera de App\Models donde HasFactory adivinaría la factory por convención.
     */
    protected static function newFactory(): PeriodoLectivoFactory
    {
        return PeriodoLectivoFactory::new();
    }
}
