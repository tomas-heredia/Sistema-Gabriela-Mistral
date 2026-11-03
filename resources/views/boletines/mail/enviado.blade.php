<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: sans-serif; color: #1c2333;">
    <h1 style="font-size: 18px;">{{ $esCorreccion ? 'Corrección de la libreta de' : 'Libreta de' }} {{ $alumno->nombre }}</h1>
    @if ($esCorreccion)
        <p>Les enviamos una versión corregida de la libreta de {{ $alumno->nombre }} que ya habíamos enviado anteriormente. Por favor, tengan en cuenta esta nueva versión en reemplazo de la anterior.</p>
    @else
        <p>Adjuntamos la libreta actualizada de {{ $alumno->nombre }}.</p>
    @endif
    <p>Saludos,<br>{{ config('app.name') }}</p>
</body>
</html>
