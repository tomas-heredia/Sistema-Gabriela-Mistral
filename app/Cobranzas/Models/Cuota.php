<?php

namespace App\Cobranzas\Models;

use App\Alumnos\Models\Alumno;
use App\Cobranzas\Models\Enums\DescuentoTipo;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Enums\TipoCuota;
use App\Core\Models\PeriodoLectivo;
use Database\Factories\CuotaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['alumno_id', 'periodo_lectivo_id', 'tipo', 'mes', 'monto_base', 'descuento_tipo', 'descuento_monto', 'monto', 'fecha_vencimiento', 'estado'])]
class Cuota extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'cuotas';

    protected function casts(): array
    {
        return [
            'tipo' => TipoCuota::class,
            'descuento_tipo' => DescuentoTipo::class,
            'estado' => EstadoCuota::class,
            'fecha_vencimiento' => 'date',
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

    public function pagoCuotas(): HasMany
    {
        return $this->hasMany(PagoCuota::class);
    }

    /**
     * Suma de lo efectivamente aplicado a esta cuota, excluyendo pagos anulados.
     */
    public function montoPagado(): int
    {
        return (int) $this->pagoCuotas()
            ->whereHas('pago', fn ($query) => $query->whereNull('anulado_at'))
            ->sum('monto_aplicado');
    }

    /**
     * Recalcula el estado a partir de lo efectivamente pagado. Es el único
     * lugar del código que debe escribir en `estado` — nunca se edita a mano.
     */
    public function recalcularEstado(): void
    {
        if ($this->estado === EstadoCuota::Anulada) {
            return;
        }

        if ($this->monto === 0) {
            $this->estado = EstadoCuota::Exenta;
            $this->save();

            return;
        }

        $pagado = $this->montoPagado();

        $this->estado = match (true) {
            $pagado <= 0 => EstadoCuota::Pendiente,
            $pagado < $this->monto => EstadoCuota::Parcial,
            default => EstadoCuota::Pagada,
        };

        $this->saveQuietly();
    }

    /**
     * Regla de CLAUDE.md: toda cuota (y su estado de pago) queda auditada.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['monto_base', 'descuento_tipo', 'descuento_monto', 'monto', 'fecha_vencimiento', 'estado'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Se declara explícito porque este modelo vive en App\Cobranzas\Models,
     * fuera de App\Models donde HasFactory adivinaría la factory por convención.
     */
    protected static function newFactory(): CuotaFactory
    {
        return CuotaFactory::new();
    }
}
