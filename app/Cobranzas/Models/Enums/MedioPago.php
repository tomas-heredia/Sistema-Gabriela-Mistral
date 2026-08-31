<?php

namespace App\Cobranzas\Models\Enums;

enum MedioPago: string
{
    case Efectivo = 'efectivo';
    case Debito = 'debito';
    case Transferencia = 'transferencia';
    case Tarjeta = 'tarjeta';
}
