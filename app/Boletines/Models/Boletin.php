<?php

namespace App\Boletines\Models;

use App\Alumnos\Models\Alumno;
use App\Boletines\Models\Enums\EstadoBoletin;
use App\Boletines\Models\Enums\EstadoTrimestre;
use App\Core\Models\PeriodoLectivo;
use Database\Factories\BoletinFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['alumno_id', 'plantilla_id', 'periodo_lectivo_id', 'estado'])]
class Boletin extends Model
{
    use HasFactory;

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
     * Completo cuando los 3 trimestres regulares (1/2/3) están enviados.
     * La Etapa de Apoyo (trimestre 4) es opcional y no bloquea "completo".
     */
    public function recalcularEstado(): void
    {
        $regularesEnviados = $this->trimestres()
            ->whereIn('trimestre', [1, 2, 3])
            ->where('estado', EstadoTrimestre::Enviado)
            ->count();

        $this->estado = $regularesEnviados === 3 ? EstadoBoletin::Completo : EstadoBoletin::EnCurso;
        $this->save();
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
