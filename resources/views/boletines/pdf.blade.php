<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Libreta — {{ $alumno->nombre }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1c2333; }
        h1 { font-size: 16px; margin-bottom: 0; }
        h2 { font-size: 13px; margin-top: 22px; border-bottom: 1px solid #999; padding-bottom: 4px; }
        h3 { font-size: 11px; margin-top: 12px; margin-bottom: 4px; }
        .meta { color: #555; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f0efe9; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $alumno->nombre }}</h1>
    <p class="meta">
        DNI: {{ $alumno->dni ?? '—' }} ·
        Nivel: {{ $alumno->nivel->value }} ·
        Grado: {{ $alumno->grado }} ·
        Período: {{ $periodo->nombre }}
    </p>

    @foreach ($trimestres as $trimestre)
        <h2>
            {{ $trimestre->trimestre === 4 ? 'Etapa de Apoyo' : "Trimestre {$trimestre->trimestre}" }}
        </h2>

        @foreach (($plantilla->estructura_campos['secciones'] ?? []) as $seccion)
            @php $valor = data_get($trimestre->datos, $seccion['id']); @endphp

            @if ($valor !== null && $valor !== [])
                <h3>{{ $seccion['titulo'] ?? $seccion['id'] }}</h3>
                @include('boletines.partials.valor-generico', ['valor' => $valor])
            @endif
        @endforeach
    @endforeach
</body>
</html>
