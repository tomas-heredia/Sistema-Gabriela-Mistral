<?php

namespace App\Boletines\Models\Enums;

enum EstadoTrimestre: string
{
    case Pendiente = 'pendiente';
    case Cargado = 'cargado';
    case Enviado = 'enviado';
}
