<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { color: #6b7280; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f3f4f6; }
        .total { margin-top: 12px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Pagos</h1>
    <p class="meta">
        @if ($desde || $hasta)
            Fecha: {{ $desde ? \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') : 'inicio' }}
            a {{ $hasta ? \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y') : 'hoy' }}
        @endif
        @if ($periodo)
            · Período: {{ $periodo->nombre }}
        @endif
        @if (! $desde && ! $hasta && ! $periodo)
            Todos los pagos
        @endif
        · Generado el {{ $generadoEl->format('d/m/Y H:i') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>N° recibo</th>
                <th>DNI alumno</th>
                <th>Apellido y Nombre</th>
                <th>Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($fila->fecha)->format('d/m/Y') }}</td>
                    <td>{{ $fila->numero_recibo }}</td>
                    <td>{{ $fila->alumno_dni }}</td>
                    <td>{{ $fila->alumno_nombre }}</td>
                    <td>${{ number_format($fila->importe / 100, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="total">
        Total: ${{ number_format($filas->sum('importe') / 100, 2, ',', '.') }} — {{ $filas->count() }} fila(s)
    </p>
</body>
</html>
