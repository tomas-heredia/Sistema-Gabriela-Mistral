<?php

namespace App\Cobranzas\Models;

use Database\Factories\PagoCuotaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pago_id', 'cuota_id', 'monto_aplicado'])]
class PagoCuota extends Model
{
    use HasFactory;

    protected $table = 'pago_cuota';

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class);
    }

    /**
     * Se declara explícito porque este modelo vive en App\Cobranzas\Models,
     * fuera de App\Models donde HasFactory adivinaría la factory por convención.
     */
    protected static function newFactory(): PagoCuotaFactory
    {
        return PagoCuotaFactory::new();
    }
}
