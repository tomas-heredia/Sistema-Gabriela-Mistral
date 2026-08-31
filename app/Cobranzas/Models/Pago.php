<?php

namespace App\Cobranzas\Models;

use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\Enums\MedioPago;
use App\Core\Models\User;
use Database\Factories\PagoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Los campos de anulación (anulado_at, anulado_por_id, motivo_anulacion) NO
 * están en Fillable a propósito: solo se escriben desde Pago::anular(), que
 * usa forceFill() — nunca por asignación masiva normal.
 */
#[Fillable(['tutor_id', 'monto', 'medio_pago', 'fecha', 'numero_recibo', 'cobrador_id', 'observaciones'])]
class Pago extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'pagos';

    protected function casts(): array
    {
        return [
            'medio_pago' => MedioPago::class,
            'fecha' => 'date',
            'anulado_at' => 'datetime',
        ];
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class);
    }

    public function cobrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cobrador_id');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por_id');
    }

    public function pagoCuotas(): HasMany
    {
        return $this->hasMany(PagoCuota::class);
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_at !== null;
    }

    /**
     * Un pago nunca se edita ni se borra (regla de CLAUDE.md): se anula, con
     * motivo y quién lo hizo, y las cuotas afectadas recalculan su estado
     * excluyendo este pago. Solo `administrador` puede anular (ver PagoPolicy).
     */
    public function anular(string $motivo, User $anuladoPor): void
    {
        if ($this->estaAnulado()) {
            return;
        }

        $this->forceFill([
            'anulado_at' => now(),
            'anulado_por_id' => $anuladoPor->id,
            'motivo_anulacion' => $motivo,
        ])->save();

        $this->pagoCuotas()
            ->with('cuota')
            ->get()
            ->each(fn (PagoCuota $pagoCuota) => $pagoCuota->cuota->recalcularEstado());
    }

    /**
     * Regla de CLAUDE.md: todo pago (y su eventual anulación) queda auditado.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['monto', 'medio_pago', 'fecha', 'numero_recibo', 'cobrador_id', 'anulado_at', 'anulado_por_id', 'motivo_anulacion'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Se declara explícito porque este modelo vive en App\Cobranzas\Models,
     * fuera de App\Models donde HasFactory adivinaría la factory por convención.
     */
    protected static function newFactory(): PagoFactory
    {
        return PagoFactory::new();
    }
}
