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

#[Fillable(['boletin_id', 'trimestre', 'datos', 'estado', 'cargado_por_id', 'fecha_enviado', 'pdf_path'])]
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
