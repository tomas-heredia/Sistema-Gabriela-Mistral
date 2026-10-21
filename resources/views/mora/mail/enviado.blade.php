<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: sans-serif; color: #1c2333;">
    <h1 style="font-size: 18px;">Resumen de deuda</h1>
    <p>Hola {{ $tutor->nombre }},</p>
    <p>
        Le enviamos el resumen de las cuotas pendientes de pago correspondientes a
        {{ $alumnosConDeuda->implode('nombre', ', ') }}. En el PDF adjunto encontrará el detalle completo,
        período por período, con los montos adeudados.
    </p>
    <p>
        Si ya realizó alguno de estos pagos y no se ve reflejado en el resumen, por favor comuníquese con la
        administración del colegio para regularizar la información.
    </p>
    <p>Saludos,<br>Colegio Privado Gabriela Mistral</p>
</body>
</html>
