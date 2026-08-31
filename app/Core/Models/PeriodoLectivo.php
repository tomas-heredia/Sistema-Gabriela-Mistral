<?php

namespace App\Core\Models;

use Database\Factories\PeriodoLectivoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
     * Se declara explícito porque este modelo vive en App\Core\Models,
     * fuera de App\Models donde HasFactory adivinaría la factory por convención.
     */
    protected static function newFactory(): PeriodoLectivoFactory
    {
        return PeriodoLectivoFactory::new();
    }
}
