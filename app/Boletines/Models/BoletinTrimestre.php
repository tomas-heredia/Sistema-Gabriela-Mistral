<?php

namespace App\Boletines\Models;

use App\Boletines\Exceptions\TransicionDeEstadoInvalidaException;
use App\Boletines\Jobs\GenerarYEnviarBoletinPdf;
use App\Boletines\Models\Enums\EstadoTrimestre;
use App\Core\Models\User;
use Database\Factories\BoletinTrimestreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

#[Fillable(['boletin_id', 'trimestre', 'datos', 'estado', 'cargado_por_id', 'fecha_enviado', 'pdf_path', 'requiere_reenvio'])]
class BoletinTrimestre extends Model
{
    use HasFactory;

    protected $table = 'boletin_trimestres';

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'estado' => EstadoTrimestre::class,
            'fecha_enviado' => 'datetime',
            'requiere_reenvio' => 'boolean',
        ];
    }

    public function boletin(): BelongsTo
    {
        return $this->belongsTo(Boletin::class);
    }

    public function cargadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cargado_por_id');
    }

    /**
     * Guarda un borrador editable. Válido en pendiente o cargado — se puede
     * seguir corrigiendo hasta confirmar el envío. Valida (liviano) que las
     * secciones informadas existan en la plantilla del boletín; no valida
     * cada campo individual dentro de una sección.
     */
    public function cargar(array $datos, User $usuario): void
    {
        if ($this->estado === EstadoTrimestre::Enviado) {
            throw new TransicionDeEstadoInvalidaException('No se puede editar un trimestre ya enviado.');
        }

        $seccionesValidas = collect($this->boletin->plantilla->estructura_campos['secciones'] ?? [])
            ->pluck('id')
            ->all();

        foreach (array_keys($datos) as $seccionId) {
            if (! in_array($seccionId, $seccionesValidas, true)) {
                throw new TransicionDeEstadoInvalidaException("La sección '{$seccionId}' no existe en la plantilla de este boletín.");
            }
        }

        $this->forceFill([
            'datos' => $datos,
            'estado' => EstadoTrimestre::Cargado,
            'cargado_por_id' => $usuario->id,
        ])->save();

        Log::channel('actividad')->info("Cargó borrador del trimestre {$this->trimestre} — {$this->boletin->alumno->nombre}", [
            'usuario_id' => $usuario->id,
            'usuario' => $usuario->name,
            'boletin_trimestre_id' => $this->id,
        ]);
    }

    /**
     * Corrige los datos de un trimestre que ya fue enviado a los tutores.
     * A diferencia de `cargar()`, no cambia el estado (sigue `enviado`) --
     * solo marca `requiere_reenvio` para que quede claro que el mail que
     * ya se mandó quedó desactualizado hasta que alguien dispare
     * `reenviarCorreccion()` explícitamente. Solo lo puede invocar un
     * administrador (ver BoletinPolicy::corregir) -- acá no se vuelve a
     * chequear el rol, autorización es responsabilidad del Livewire/Policy.
     */
    public function corregir(array $datos, User $usuario): void
    {
        if ($this->estado !== EstadoTrimestre::Enviado) {
            throw new TransicionDeEstadoInvalidaException('Solo se puede corregir un trimestre que ya fue enviado -- para uno sin enviar, usá cargar().');
        }

        $seccionesValidas = collect($this->boletin->plantilla->estructura_campos['secciones'] ?? [])
            ->pluck('id')
            ->all();

        foreach (array_keys($datos) as $seccionId) {
            if (! in_array($seccionId, $seccionesValidas, true)) {
                throw new TransicionDeEstadoInvalidaException("La sección '{$seccionId}' no existe en la plantilla de este boletín.");
            }
        }

        $this->forceFill([
            'datos' => $datos,
            'requiere_reenvio' => true,
        ])->save();

        Log::channel('actividad')->info("Corrigió el trimestre ya enviado {$this->trimestre} — {$this->boletin->alumno->nombre}", [
            'usuario_id' => $usuario->id,
            'usuario' => $usuario->name,
            'boletin_trimestre_id' => $this->id,
        ]);
    }

    /**
     * Sin aprobación de un segundo actor: quien carga es quien confirma el
     * envío. Acción explícita, separada de "cargar", para no mandar el mail
     * a mitad de la carga. El job real (PDF + mail) corre encolado.
     */
    public function confirmarYEnviar(): void
    {
        if ($this->estado !== EstadoTrimestre::Cargado) {
            throw new TransicionDeEstadoInvalidaException('Solo se puede confirmar el envío de un trimestre cargado.');
        }

        GenerarYEnviarBoletinPdf::dispatch($this);

        $usuario = auth()->user();

        Log::channel('actividad')->info("Confirmó y envió el trimestre {$this->trimestre} — {$this->boletin->alumno->nombre}", [
            'usuario_id' => $usuario?->id,
            'usuario' => $usuario?->name,
            'boletin_trimestre_id' => $this->id,
        ]);
    }

    /**
     * Reenvía el PDF (regenerado con los datos corregidos) a los tutores,
     * aclarando en el mail que es una corrección. Acción explícita y
     * separada de `corregir()` por el mismo motivo que `confirmarYEnviar()`
     * está separado de `cargar()`: corregir datos y volver a mandar el mail
     * son dos decisiones distintas, no una consecuencia automática de la
     * otra.
     */
    public function reenviarCorreccion(): void
    {
        if ($this->estado !== EstadoTrimestre::Enviado || ! $this->requiere_reenvio) {
            throw new TransicionDeEstadoInvalidaException('No hay ninguna corrección pendiente de reenvío para este trimestre.');
        }

        GenerarYEnviarBoletinPdf::dispatch($this, esCorreccion: true);

        $usuario = auth()->user();

        Log::channel('actividad')->info("Reenvió por corrección el trimestre {$this->trimestre} — {$this->boletin->alumno->nombre}", [
            'usuario_id' => $usuario?->id,
            'usuario' => $usuario?->name,
            'boletin_trimestre_id' => $this->id,
        ]);
    }

    /**
     * Se declara explícito porque este modelo vive en App\Boletines\Models,
     * fuera de App\Models donde HasFactory adivinaría la factory por convención.
     */
    protected static function newFactory(): BoletinTrimestreFactory
    {
        return BoletinTrimestreFactory::new();
    }
}
