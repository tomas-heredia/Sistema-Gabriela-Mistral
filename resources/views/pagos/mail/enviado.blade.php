<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: sans-serif; color: #1c2333;">
    <h1 style="font-size: 18px;">Comprobante de pago — Recibo N° {{ $numeroRecibo }}</h1>
    <p>Adjuntamos el comprobante del pago registrado para {{ $alumno->nombre }}.</p>
    <p>Saludos,<br>{{ config('app.name') }}</p>
</body>
</html>
