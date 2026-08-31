<?php

namespace App\Alumnos\Models;

use Database\Factories\TutorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tutor extends Model
{
    use HasFactory;

    /**
     * Se declara explícito porque la pluralización automática de Eloquent
     * asume inglés: "Tutor" adivinaría "tutors", no "tutores".
     */
    protected $table = 'tutores';

    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'alumno_tutor')
            ->withPivot('vinculo', 'responsable_pago')
            ->withTimestamps();
    }

    /**
     * Se declara explícito porque este modelo vive en App\Alumnos\Models,
     * fuera de App\Models donde HasFactory adivinaría la factory por convención.
     */
    protected static function newFactory(): TutorFactory
    {
        return TutorFactory::new();
    }
}
