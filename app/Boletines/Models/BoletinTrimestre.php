<?php

namespace App\Boletines\Models;

use App\Alumnos\Models\Enums\Nivel;
use App\Boletines\Exceptions\TransicionDeEstadoInvalidaException;
use App\Boletines\Jobs\GenerarYEnviarBoletinPdf;
use App\Boletines\Models\Enums\EstadoTrimestre;
use App\Boletines\Models\Enums\TipoTrimestre;
use App\Core\Models\User;
use Database\Factories\BoletinTrimestreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

/**
 * `trimestre` ya no es siempre 1/2/3: a partir de este cambio, 4/5/6 tienen
 * un significado fijo (ver TipoTrimestre::tipo() más abajo) --
 * Recuperación Diciembre / Recuperación Febrero / Libreta Final -- en vez
 * de la vieja "Etapa de Apoyo" (que mostraba todas las materias, no solo
 * las desaprobadas, y se creaba a mano). Reutiliza el mismo campo entero
 * sin agregar columnas nuevas.
 */
#[Fillable(['boletin_id', 'trimestre', 'datos', 'estado', 'cargado_por_id', 'fecha_enviado', 'pdf_path', 'requiere_reenvio'])]
class BoletinTrimestre extends Model
{
    use HasFactory;

    public const TRIMESTRE_RECUPERACION_DICIEMBRE = 4;

    public const TRIMESTRE_RECUPERACION_FEBRERO = 5;

    public const TRIMESTRE_LIBRETA_FINAL = 6;

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

    public function tipo(): TipoTrimestre
    {
        return match ($this->trimestre) {
            self::TRIMESTRE_RECUPERACION_DICIEMBRE => TipoTrimestre::RecuperacionDiciembre,
            self::TRIMESTRE_RECUPERACION_FEBRERO => TipoTrimestre::RecuperacionFebrero,
            self::TRIMESTRE_LIBRETA_FINAL => TipoTrimestre::LibretaFinal,
            default => TipoTrimestre::Regular,
        };
    }

    /**
     * Etiqueta que ve el cobrador/director: "PAeIP" en primaria, "ICAP" en
     * secundaria, para las dos instancias de recuperación -- mismo nombre
     * de pila que antes tenía "Etapa de Apoyo", ahora centralizado acá en
     * vez de repetido como `=== 4` en cada vista.
     */
    public function etiqueta(): string
    {
        $recuperacion = $this->boletin->alumno->nivel === Nivel::Primario ? 'PAeIP' : 'ICAP';

        return match ($this->tipo()) {
            TipoTrimestre::RecuperacionDiciembre => "Recuperación Diciembre ({$recuperacion})",
            TipoTrimestre::RecuperacionFebrero => "Recuperación Febrero ({$recuperacion})",
            TipoTrimestre::LibretaFinal => 'Libreta Final',
            TipoTrimestre::Regular => "Trimestre {$this->trimestre}",
        };
    }

    /**
     * Versión corta de `etiqueta()` para espacios chicos (la pill del
     * listado de boletines).
     */
    public function etiquetaCorta(): string
    {
        $recuperacion = $this->boletin->alumno->nivel === Nivel::Primario ? 'PAeIP' : 'ICAP';

        return match ($this->tipo()) {
            TipoTrimestre::RecuperacionDiciembre => "{$recuperacion} Dic",
            TipoTrimestre::RecuperacionFebrero => "{$recuperacion} Feb",
            TipoTrimestre::LibretaFinal => 'Final',
            TipoTrimestre::Regular => "T{$this->trimestre}",
        };
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
