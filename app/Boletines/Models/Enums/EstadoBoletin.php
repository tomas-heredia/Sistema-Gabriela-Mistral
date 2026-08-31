<?php

namespace App\Boletines\Models\Enums;

enum EstadoBoletin: string
{
    case EnCurso = 'en_curso';
    case Completo = 'completo';
}
