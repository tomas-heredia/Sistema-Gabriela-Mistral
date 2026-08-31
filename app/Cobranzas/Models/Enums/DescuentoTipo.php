<?php

namespace App\Cobranzas\Models\Enums;

enum DescuentoTipo: string
{
    case Ninguno = 'ninguno';
    case Hermanos = 'hermanos';
    case Beca = 'beca';
}
