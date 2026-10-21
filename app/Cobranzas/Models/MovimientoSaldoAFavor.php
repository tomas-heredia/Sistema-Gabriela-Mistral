<?php

namespace App\Cobranzas\Models;

use App\Alumnos\Models\Tutor;
use Database\Factories\MovimientoSaldoAFavorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un movimiento por pago que generó o consumió saldo a favor -- nunca se
 * edita ni se borra (mismo criterio que Pago). El saldo disponible de un
 * tutor es la suma de sus movimientos, calculada al momento (ver
 * App\Cobranzas\Services\CalculadorDeSaldoAFavor), nunca una columna cacheada.
 */
#[Fillable(['tutor_id', 'pago_id', 'monto'])]
class MovimientoSaldoAFavor extends Model
{
    use HasFactory;

    protected $table = 'movimientos_saldo_a_favor';

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class);
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    /**
     * Se declara explícito porque este modelo vive en App\Cobranzas\Models,
     * fuera de App\Models donde HasFactory adivinaría la factory por convención.
     */
    protected static function newFactory(): MovimientoSaldoAFavorFactory
    {
        return MovimientoSaldoAFavorFactory::new();
    }
}
