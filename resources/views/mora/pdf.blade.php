<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Tutores en mora</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1c2333; }
        h1 { font-size: 16px; margin-bottom: 0; }
        .meta { color: #555; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f0efe9; font-weight: bold; }
        .total { margin-top: 12px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Tutores en mora</h1>
    <p class="meta">
        Período: {{ $periodo?->nombre ?? 'Todos' }} ·
        Generado el {{ $generadoEl->format('d/m/Y H:i') }}
    </p>

    @if ($morosos->isEmpty())
        <p>No hay ningún tutor en mora que corresponda con este filtro.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Tutor</th>
                    <th>DNI</th>
                    <th>Monto adeudado</th>
                    <th>Meses adeudados</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($morosos as $fila)
                    <tr>
                        <td>{{ $fila['tutor']->nombre }}</td>
                        <td>{{ $fila['tutor']->dni }}</td>
                        <td>${{ number_format($fila['monto_adeudado'] / 100, 2, ',', '.') }}</td>
                        <td>{{ $fila['meses_adeudados'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="total">
            Total adeudado: ${{ number_format($morosos->sum('monto_adeudado') / 100, 2, ',', '.') }}
            — {{ $morosos->count() }} {{ $morosos->count() === 1 ? 'tutor' : 'tutores' }}
        </p>
    @endif
</body>
</html>
