<?php

namespace App\Mora\Models;

use App\Alumnos\Models\Tutor;
use App\Mora\Models\Enums\EstadoNotificacion;
use Database\Factories\NotificacionMoraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tutor_id', 'monto_adeudado', 'meses_adeudados', 'estado', 'fecha', 'fecha_envio', 'intentos'])]
class NotificacionMora extends Model
{
    use HasFactory;

    protected $table = 'notificaciones_mora';

    protected function casts(): array
    {
        return [
            'estado' => EstadoNotificacion::class,
            'fecha' => 'date',
            'fecha_envio' => 'datetime',
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
    protected static function newFactory(): NotificacionMoraFactory
    {
        return NotificacionMoraFactory::new();
    }
}
