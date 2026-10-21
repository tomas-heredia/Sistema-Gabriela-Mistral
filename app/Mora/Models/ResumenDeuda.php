<?php

namespace App\Mora\Models;

use App\Alumnos\Models\Tutor;
use Database\Factories\ResumenDeudaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de cada resumen mensual de deuda ya enviado a un tutor -- además
 * de auditoría, el par (tutor_id, periodo) es lo que evita reenviar el mismo
 * mes dos veces si el comando corre más de una vez (ver GenerarResumenesMensualesDeDeuda).
 */
#[Fillable(['tutor_id', 'periodo', 'monto_adeudado', 'pdf_path', 'enviado_at'])]
class ResumenDeuda extends Model
{
    use HasFactory;

    protected $table = 'resumenes_deuda';

    protected function casts(): array
    {
        return [
            'enviado_at' => 'datetime',
        ];
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class);
    }

    /**
     * Se declara explícito porque este modelo vive en App\Mora\Models,
     * fuera de App\Models donde HasFactory adivinaría la factory por convención.
     */
    protected static function newFactory(): ResumenDeudaFactory
    {
        return ResumenDeudaFactory::new();
    }
}
