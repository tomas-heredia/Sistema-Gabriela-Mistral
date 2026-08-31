<?php

namespace App\Cobranzas\Models\Enums;

enum EstadoCuota: string
{
    case Pendiente = 'pendiente';
    case Parcial = 'parcial';
    case Pagada = 'pagada';
    case Exenta = 'exenta';
    case Anulada = 'anulada';
}
