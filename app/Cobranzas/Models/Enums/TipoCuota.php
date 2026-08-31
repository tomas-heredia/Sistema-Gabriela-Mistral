<?php

namespace App\Cobranzas\Models\Enums;

enum TipoCuota: string
{
    case Matricula = 'matricula';
    case Mensualidad = 'mensualidad';
}
