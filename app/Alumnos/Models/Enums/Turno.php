<?php

namespace App\Alumnos\Models\Enums;

enum Turno: string
{
    case Manana = 'mañana';
    case Tarde = 'tarde';
    case Completo = 'completo';
}
