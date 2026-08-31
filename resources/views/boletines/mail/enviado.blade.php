<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: sans-serif; color: #1c2333;">
    <h1 style="font-size: 18px;">Boletín de {{ $alumno->nombre }}</h1>
    <p>Adjuntamos el boletín actualizado de {{ $alumno->nombre }}.</p>
    <p>Saludos,<br>{{ config('app.name') }}</p>
</body>
</html>
