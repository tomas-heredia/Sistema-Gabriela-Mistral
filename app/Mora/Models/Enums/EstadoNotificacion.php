<?php

namespace App\Mora\Models\Enums;

enum EstadoNotificacion: string
{
    case Pendiente = 'pendiente';
    case Enviado = 'enviado';
    case Fallido = 'fallido';
    case Cancelado = 'cancelado';
}
