<?php

namespace App\Sueldos\Models;

use App\Core\Models\User;
use Database\Factories\ReciboSueldoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['profesor_id', 'periodo', 'archivo', 'cargado_por_id', 'fecha_carga'])]
class ReciboSueldo extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'recibos_sueldo';

    protected function casts(): array
    {
        return [
            'fecha_carga' => 'date',
        ];
    }

    public function profesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesor_id');
    }

    public function cargadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cargado_por_id');
    }

    /**
     * Regla de CLAUDE.md: todo recibo de sueldo queda auditado.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['profesor_id', 'periodo', 'archivo', 'cargado_por_id', 'fecha_carga'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Se declara explícito porque este modelo vive en App\Sueldos\Models,
     * fuera de App\Models donde HasFactory adivinaría la factory por convención.
     */
    protected static function newFactory(): ReciboSueldoFactory
    {
        return ReciboSueldoFactory::new();
    }
}
