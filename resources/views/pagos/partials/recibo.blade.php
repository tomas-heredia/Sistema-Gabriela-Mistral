<table class="membrete">
    <tr>
        <td class="logo">
            <img src="{{ public_path('images/logo.png') }}" alt="">
        </td>
        <td class="datos">
            <div class="nombre-colegio">COLEGIO PRIVADO GABRIELA MISTRAL</div>
            <div class="linea">Adscripto a la Enseñanza Oficial</div>
            <div class="linea">Decretos N° 1189, 1037 y 417</div>
            <div class="linea">Nivel Inicial - Primario - Secundario</div>
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
    <p><span class="label">Forma de Pago:</span> {{ $formaDePago }}</p>
</div>

<table class="montos">
    <thead>
        <tr>
            <th>Período</th>
            <th class="importe">SubTotal</th>
            <th class="importe">Desc.</th>
            <th class="importe">Interés</th>
            <th class="importe">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lineas as $linea)
            <tr>
                <td>{{ $linea['periodo'] }}</td>
                <td class="importe">${{ number_format($linea['subtotal'] / 100, 2, ',', '.') }}</td>
                <td class="importe">${{ number_format($linea['descuento'] / 100, 2, ',', '.') }}</td>
                <td class="importe">${{ number_format($linea['interes'] / 100, 2, ',', '.') }}</td>
                <td class="importe">${{ number_format($linea['total'] / 100, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="total">
            <td>TOTAL</td>
            <td class="importe">${{ number_format($subtotal / 100, 2, ',', '.') }}</td>
            <td class="importe">${{ number_format($descuento / 100, 2, ',', '.') }}</td>
            <td class="importe">${{ number_format($interes / 100, 2, ',', '.') }}</td>
            <td class="importe">${{ number_format($total / 100, 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

<p class="son">SON: {{ $totalEnLetras }}</p>
