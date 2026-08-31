<?php

namespace App\Cobranzas\Models;

use App\Alumnos\Models\Enums\Nivel;
use App\Cobranzas\Models\Enums\TipoCuota;
use App\Core\Models\PeriodoLectivo;
use Database\Factories\ArancelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['periodo_lectivo_id', 'nivel', 'tipo', 'monto'])]
class Arancel extends Model
{
    use HasFactory;

    protected $table = 'aranceles';

    protected function casts(): array
    {
        return [
            'nivel' => Nivel::class,
            'tipo' => TipoCuota::class,
        ];
    }

    public function periodoLectivo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLectivo::class);
    }

    /**
     * Se declara explícito porque este modelo vive en App\Cobranzas\Models,
     * fuera de App\Models donde HasFactory adivinaría la factory por convención.
     */
    protected static function newFactory(): ArancelFactory
    {
        return ArancelFactory::new();
    }
}
