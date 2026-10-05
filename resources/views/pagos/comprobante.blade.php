<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo N° {{ $numeroRecibo }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1c2333; }
        .membrete { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .membrete td { border: none; padding: 0; vertical-align: middle; }
        .membrete .logo { width: 80px; }
        .membrete .logo img { width: 64px; }
        .membrete .datos { text-align: center; }
        .membrete .datos .nombre-colegio { font-size: 15px; font-weight: bold; margin-bottom: 2px; }
        .membrete .datos .linea { margin-bottom: 2px; }
        .membrete .datos .de { font-style: italic; margin: 6px 0; }
        hr { border: none; border-top: 1px solid #999; margin-bottom: 12px; }
        .encabezado-recibo { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .encabezado-recibo td { border: none; padding: 0; }
        .encabezado-recibo .fecha { text-align: right; }
        .datos-pago p { margin: 4px 0; }
        .label { color: #444; }
        table.montos { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.montos td { border: none; padding: 3px 0; }
        table.montos .importe { text-align: right; }
        table.montos .total td { border-top: 1px solid #999; padding-top: 6px; font-weight: bold; font-size: 13px; }
        .son { margin-top: 14px; font-style: italic; }
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
                <div class="linea">Decretos N° 1189, 1037 y 417</div>
                <div class="linea">Niveles Inicial - EGB y Polimodal</div>
                <div class="linea">Cuit: 20179395593 &nbsp;&nbsp;&nbsp; Ingreso Bruto: 58389</div>
                <div class="de">De: HEREDIA OMAR EDUARDO</div>
                <div class="linea">VICENTE LOPEZ S/N &nbsp;&nbsp;&nbsp; Tel: 03835-423003</div>
                <div class="linea">ANDALGALA - CATAMARCA</div>
            </td>
        </tr>
    </table>

    <hr>

    <table class="encabezado-recibo">
        <tr>
            <td><strong>RECIBO N°:</strong> {{ $numeroRecibo }}</td>
            <td class="fecha">{{ $fecha->format('d/m/Y') }}</td>
        </tr>
    </table>

    <div class="datos-pago">
        <p><span class="label">Tutor:</span> {{ $tutorNombre }} - DNI: {{ $tutorDni }}</p>
        <p><span class="label">Alumno:</span> {{ $alumnoNombre }} - DNI: {{ $alumnoDni }}</p>
        <p><span class="label">Nivel:</span> {{ $nivel }}</p>
        <p><span class="label">Grado:</span> {{ $gradoLinea }}</p>
        <p><span class="label">Período:</span> {{ $periodoLinea }}</p>
        <p><span class="label">Forma de Pago:</span> {{ $formaDePago }}</p>
    </div>

    <table class="montos">
        <tr>
            <td>SubTotal</td>
            <td class="importe">${{ number_format($subtotal / 100, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Desc.</td>
            <td class="importe">${{ number_format($descuento / 100, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Interés</td>
            <td class="importe">${{ number_format($interes / 100, 2, ',', '.') }}</td>
        </tr>
        <tr class="total">
            <td>TOTAL</td>
            <td class="importe">${{ number_format($total / 100, 2, ',', '.') }}</td>
        </tr>
    </table>

    <p class="son">SON: {{ $totalEnLetras }}</p>
</body>
</html>
