<?php

namespace App\Sueldos\Models;

use Database\Factories\ReciboSueldoArchivoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['recibo_sueldo_id', 'archivo'])]
class ReciboSueldoArchivo extends Model
{
    use HasFactory;

    protected $table = 'recibo_sueldo_archivos';

    public function reciboSueldo(): BelongsTo
    {
        return $this->belongsTo(ReciboSueldo::class);
    }

    /**
     * Se declara explícito porque este modelo vive en App\Sueldos\Models,
     * fuera de App\Models donde HasFactory adivinaría la factory por convención.
     */
    protected static function newFactory(): ReciboSueldoArchivoFactory
    {
        return ReciboSueldoArchivoFactory::new();
    }
}
