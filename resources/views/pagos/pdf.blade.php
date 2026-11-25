<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1c2333; }
        h1 { font-size: 16px; margin-bottom: 14px; }
        table.pagos { width: 100%; border-collapse: collapse; }
        table.pagos th, table.pagos td { border-bottom: 1px solid #ddd; padding: 5px 8px; text-align: left; }
        table.pagos thead th { border-bottom: 1px solid #999; }
        table.pagos .importe { text-align: right; }
    </style>
</head>
<body>
    <h1>Pagos ({{ now()->format('d/m/Y') }})</h1>

    <table class="pagos">
        <thead>
            <tr>
                <th>Tutor</th>
                <th class="importe">Monto</th>
                <th>Medio</th>
                <th>Fecha</th>
                <th>N° recibo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pagos as $pago)
                <tr>
                    <td>{{ $pago->tutor->nombre }}</td>
                    <td class="importe">${{ number_format($pago->monto / 100, 2, ',', '.') }}</td>
                    <td>{{ $pago->medio_pago->label() }}</td>
                    <td>{{ $pago->fecha->format('d/m/Y') }}</td>
                    <td>{{ $pago->pagoCuotas->pluck('numero_recibo')->unique()->implode(', ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
