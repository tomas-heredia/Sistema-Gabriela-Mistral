<?php

namespace App\Boletines\Models;

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Enums\Nivel;
use App\Boletines\Models\Enums\EstadoBoletin;
use App\Boletines\Models\Enums\EstadoTrimestre;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Database\Factories\BoletinFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['alumno_id', 'plantilla_id', 'periodo_lectivo_id', 'estado'])]
class Boletin extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'boletines';

    protected function casts(): array
    {
        return [
            'estado' => EstadoBoletin::class,
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaBoletin::class, 'plantilla_id');
    }

    public function periodoLectivo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLectivo::class);
    }

    public function trimestres(): HasMany
    {
        return $this->hasMany(BoletinTrimestre::class);
    }

    /**
     * Secundaria ya no tiene recuperación/Libreta Final encadenada (ICAIP
     * Diciembre/Febrero pasaron a ser columnas del propio 3er trimestre,
     * ver GestorDeRecuperaciones) -- completo apenas se envía ese trimestre.
     * Primaria sigue como antes: completo recién cuando la Libreta Final
     * (trimestre 6) está enviada, porque un alumno con materias desaprobadas
     * sigue "en curso" hasta terminar su recuperación (PAeIP) y recibirla,
     * apruebe o no.
     */
    public function recalcularEstado(): void
    {
        $completo = $this->alumno->nivel === Nivel::Secundario
            ? $this->trimestres()->where('trimestre', 3)->where('estado', EstadoTrimestre::Enviado)->exists()
            : $this->trimestres()->where('trimestre', BoletinTrimestre::TRIMESTRE_LIBRETA_FINAL)->where('estado', EstadoTrimestre::Enviado)->exists();

        $this->estado = $completo ? EstadoBoletin::Completo : EstadoBoletin::EnCurso;
        $this->save();
    }

    /**
     * Borra la libreta completa: sus trimestres (el FK de boletin_trimestres
     * es restrictOnDelete, así que hay que borrarlos antes) y los PDFs ya
     * generados en storage. Pensado para corregir un alta equivocada (ej.
     * plantilla incorrecta) y volver a crear la libreta desde cero para el
     * mismo período -- no revierte mails ya mandados a los tutores.
     */
    public function eliminarConTrimestres(User $usuario): void
    {
        $alumnoNombre = $this->alumno->nombre;
        $boletinId = $this->id;

        Storage::disk('local')->deleteDirectory("boletines/generados/{$this->id}");
        $this->trimestres()->delete();
        $this->delete();

        Log::channel('actividad')->info("Eliminó la libreta de {$alumnoNombre}", [
            'usuario_id' => $usuario->id,
            'usuario' => $usuario->name,
            'boletin_id' => $boletinId,
        ]);
    }

    /**
     * Auditoría interna (no hay pantalla que la muestre -- se consulta
     * directo en la tabla `activity_log`, igual que la de cobros/mora):
     * quién crea o borra una libreta, y cuándo pasa de en_curso a completo.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['alumno_id', 'plantilla_id', 'periodo_lectivo_id', 'estado'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Se declara explícito porque este modelo vive en App\Boletines\Models,
     * fuera de App\Models donde HasFactory adivinaría la factory por convención.
     */
    protected static function newFactory(): BoletinFactory
    {
        return BoletinFactory::new();
    }
}
