<?php

namespace App\Cobranzas\Models;

use App\Alumnos\Models\Alumno;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Database\Factories\BecaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['alumno_id', 'periodo_lectivo_id', 'motivo', 'aprobado_por_id', 'fecha_otorgamiento'])]
class Beca extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'becas';

    protected function casts(): array
    {
        return [
            'fecha_otorgamiento' => 'date',
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

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_id');
    }

    /**
     * Regla de CLAUDE.md: toda beca (exención total de cuotas) queda auditada.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['alumno_id', 'periodo_lectivo_id', 'motivo', 'aprobado_por_id', 'fecha_otorgamiento'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Se declara explícito porque este modelo vive en App\Cobranzas\Models,
     * fuera de App\Models donde HasFactory adivinaría la factory por convención.
     */
    protected static function newFactory(): BecaFactory
    {
        return BecaFactory::new();
    }
}
