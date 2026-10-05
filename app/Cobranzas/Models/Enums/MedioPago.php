<?php

namespace App\Cobranzas\Models\Enums;

enum MedioPago: string
{
    case Efectivo = 'efectivo';
    case Debito = 'debito';
    case Transferencia = 'transferencia';
    case TarjetaCredito = 'tarjeta_credito';
    case TarjetaDebito = 'tarjeta_debito';
    case DescuentoPlanilla = 'descuento_planilla';

    public function label(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Debito => 'Débito',
            self::Transferencia => 'Transferencia',
            self::TarjetaCredito => 'Tarjeta de crédito',
            self::TarjetaDebito => 'Tarjeta de débito',
            self::DescuentoPlanilla => 'Descuento por planilla',
        };
    }
}
