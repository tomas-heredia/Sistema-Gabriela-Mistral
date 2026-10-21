<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1c2333; }
        .membrete { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .membrete td { border: none; padding: 0; vertical-align: middle; }
        .membrete .logo { width: 80px; }
        .membrete .logo img { width: 64px; }
        .membrete .datos { text-align: center; }
        .membrete .datos .nombre-colegio { font-size: 15px; font-weight: bold; margin-bottom: 2px; }
        .membrete .datos .linea { margin-bottom: 2px; }
        hr { border: none; border-top: 1px solid #999; margin-bottom: 12px; }
        .titulo { text-align: center; font-size: 14px; font-weight: bold; margin-bottom: 4px; letter-spacing: 0.5px; }
        .fecha-emision { text-align: center; color: #666; margin-bottom: 16px; }
        .datos-tutor p { margin: 4px 0; }
        .label { color: #444; }
        .alumno-bloque { margin-top: 18px; }
        .alumno-bloque + .alumno-bloque { page-break-inside: avoid; }
        .alumno-nombre { font-size: 13px; font-weight: bold; margin-bottom: 6px; }
        table.cuotas { width: 100%; border-collapse: collapse; }
        table.cuotas th, table.cuotas td { border: none; padding: 3px 6px 3px 0; text-align: left; }
        table.cuotas thead th { border-bottom: 1px solid #999; padding-bottom: 4px; }
        table.cuotas .importe { text-align: right; }
        table.cuotas .subtotal td { border-top: 1px solid #ccc; padding-top: 4px; font-weight: bold; }
        .total-general { margin-top: 20px; padding-top: 10px; border-top: 2px solid #1c2333; text-align: right; font-size: 15px; font-weight: bold; }
        .nota { margin-top: 24px; font-size: 11px; color: #555; line-height: 1.5; }
    </style>
</head>
<body>
    <table class="membrete">
        <tr>
            <td class="logo">
                <img src="{{ public_path('images/logo.png') }}" alt="">
            </td>
            <td class="datos">
                <div class="nombre-colegio">COLEGIO PRIVADO GABRIELA MISTRAL</div>
                <div class="linea">Adscripto a la Enseñanza Oficial</div>
                <div class="linea">Nivel Inicial - Primario - Secundario</div>
                <div class="linea">VICENTE LOPEZ S/N &nbsp;&nbsp;&nbsp; Tel: 03835-423003</div>
                <div class="linea">ANDALGALA - CATAMARCA</div>
            </td>
        </tr>
    </table>

    <hr>

    <div class="titulo">RESUMEN DE DEUDA</div>
    <div class="fecha-emision">Emitido el {{ $fechaEmision->translatedFormat('d \\d\\e F \\d\\e Y') }}</div>

    <div class="datos-tutor">
        <p><span class="label">Tutor:</span> {{ $tutor->nombre }} — DNI: {{ $tutor->dni }}</p>
    </div>

    @foreach ($detalle as $fila)
        <div class="alumno-bloque">
            <div class="alumno-nombre">{{ $fila['alumno']->nombre }}</div>

            <table class="cuotas">
                <thead>
                    <tr>
                        <th>Período</th>
                        <th>Vencimiento</th>
                        <th class="importe">Monto</th>
                        <th class="importe">Pagado</th>
                        <th class="importe">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($fila['cuotas'] as $linea)
                        <tr>
                            <td>
                                @if ($linea['cuota']->tipo->value === 'matricula')
                                    Matrícula
                                @else
                                    {{ ucfirst($linea['cuota']->fecha_vencimiento->translatedFormat('F Y')) }}
                                @endif
                            </td>
                            <td>{{ $linea['cuota']->fecha_vencimiento->format('d/m/Y') }}</td>
                            <td class="importe">${{ number_format($linea['cuota']->monto / 100, 2, ',', '.') }}</td>
                            <td class="importe">${{ number_format($linea['pagado'] / 100, 2, ',', '.') }}</td>
                            <td class="importe">${{ number_format($linea['saldo'] / 100, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="subtotal">
                        <td colspan="4">Subtotal {{ $fila['alumno']->nombre }}</td>
                        <td class="importe">${{ number_format($fila['subtotal'] / 100, 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endforeach

    <div class="total-general">
        TOTAL ADEUDADO: ${{ number_format($totalAdeudado / 100, 2, ',', '.') }}
    </div>

    <p class="nota">
        Este resumen refleja las cuotas vencidas e impagas al momento de su emisión. Si ya abonó alguno de estos
        períodos y los pagos no se reflejan todavía, por favor comuníquese con la administración del colegio para
        regularizar la información.
    </p>
</body>
</html>
