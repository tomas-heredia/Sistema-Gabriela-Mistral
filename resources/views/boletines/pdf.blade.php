<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Libreta — {{ $alumno->nombre }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1c2333; }
        h1 { font-size: 16px; margin-bottom: 0; }
        h2 { font-size: 13px; margin-top: 10px; border-bottom: 1px solid #999; padding-bottom: 4px; }
        h3 { font-size: 11px; margin-top: 12px; margin-bottom: 4px; }
        .encabezado-ministerio { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .encabezado-ministerio td { border: none; padding: 0; vertical-align: middle; font-size: 9px; line-height: 1.3; }
        .encabezado-ministerio .logo-ministerio { width: 50px; padding-right: 8px; }
        .encabezado-ministerio .logo-ministerio img { width: 42px; }
        .encabezado-ministerio .titulo-principal { font-weight: bold; font-size: 10px; }
        .nombre-colegio { font-size: 14px; font-weight: bold; text-align: center; margin: 0; }
        .titulo-informe { font-size: 13px; font-weight: bold; text-align: center; margin: 4px 0 0; }
        .subtitulo-nivel { font-size: 11px; font-weight: bold; text-align: center; margin: 0 0 10px; }
        .meta { color: #555; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f0efe9; font-weight: bold; }
        .logo-pagina { position: fixed; top: 0; right: 0; width: 70px; }
        .trimestre + .trimestre { page-break-before: always; }
    </style>
</head>
<body>
    <img class="logo-pagina" src="{{ public_path('images/logo.png') }}" alt="">

    @foreach ($trimestres as $trimestre)
        <div class="trimestre">
            @php
                $subtituloNivel = match (true) {
                    $alumno->nivel === \App\Alumnos\Models\Enums\Nivel::Primario => 'NIVEL PRIMARIO',
                    $alumno->nivel === \App\Alumnos\Models\Enums\Nivel::Secundario && $alumno->anio_secundaria <= 3 => 'NIVEL SECUNDARIO CICLO BÁSICO',
                    $alumno->nivel === \App\Alumnos\Models\Enums\Nivel::Secundario => 'NIVEL SECUNDARIO CICLO ORIENTADO',
                    default => null,
                };
            @endphp
            <table class="encabezado-ministerio">
                <tr>
                    <td class="logo-ministerio"><img src="{{ public_path('images/ministerio-logo.jpeg') }}" alt=""></td>
                    <td>
                        <span class="titulo-principal">Ministerio de Educación y Trabajo</span><br>
                        Catamarca Gobierno
                    </td>
                    <td>Secretaría de<br>Gestión Educativa</td>
                    <td>Dirección Provincial de Educación<br>de Gestión Municipal, Privada,<br>Social y Cooperativa</td>
                </tr>
            </table>
            <p class="nombre-colegio">COLEGIO PRIVADO GABRIELA MISTRAL</p>
            <p class="titulo-informe">INFORME INDIVIDUAL DE EVALUACIÓN DE APRENDIZAJES</p>
            @if ($subtituloNivel)
                <p class="subtitulo-nivel">{{ $subtituloNivel }}</p>
            @endif
            <p class="meta">C.U.E.: 1000770-00</p>
            <h1>{{ $alumno->nombre }}</h1>
            <p class="meta">
                DNI: {{ $alumno->dni ?? '—' }} ·
                Nivel: {{ $alumno->nivel->value }} ·
                Grado: {{ $alumno->grado }} ·
                División: {{ $alumno->division ?? '—' }} ·
                Turno: {{ $alumno->turno === \App\Alumnos\Models\Enums\Turno::Manana ? 'M' : 'T' }} ·
                Período: {{ $periodo->nombre }}
            </p>

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
        </div>
    @endforeach
</body>
</html>
