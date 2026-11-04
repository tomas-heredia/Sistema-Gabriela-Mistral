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
        .subtitulo-nivel { font-size: 11px; font-weight: bold; text-align: center; margin: 0 0 4px; }
        .orientacion { font-size: 10px; text-align: center; margin: 0 0 6px; }
        .normativa { font-size: 8px; color: #555; text-align: center; line-height: 1.3; margin: 0 0 10px; }
        .meta { color: #555; margin-bottom: 8px; }
        .leyenda-seleccion { margin: 10px 0; }
        .leyenda-seleccion strong { text-transform: uppercase; }
        .leyenda-seleccion .opcion-elegida { font-weight: bold; text-decoration: underline; }
        .campo-calculado { margin: 10px 0; font-size: 12px; }
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
                $esCicloOrientado = $alumno->nivel === \App\Alumnos\Models\Enums\Nivel::Secundario && $alumno->anio_secundaria > 3;
                $subtituloNivel = match (true) {
                    $alumno->nivel === \App\Alumnos\Models\Enums\Nivel::Primario => 'NIVEL PRIMARIO',
                    $alumno->nivel === \App\Alumnos\Models\Enums\Nivel::Secundario && $alumno->anio_secundaria <= 3 => 'NIVEL SECUNDARIO CICLO BÁSICO',
                    $esCicloOrientado => 'NIVEL SECUNDARIO CICLO ORIENTADO',
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
            <p class="titulo-informe">Informe Individual de Seguimiento Pedagógico</p>
            @if ($subtituloNivel)
                <p class="subtitulo-nivel">{{ $subtituloNivel }}</p>
            @endif
            @if ($esCicloOrientado)
                <p class="orientacion">Orientación: Cs. Soc. y Hum.</p>
            @endif
            <p class="normativa">
                Ley de Educación Nacional N° 26206/06<br>
                Ley de Educación Provincial N° 5381/14<br>
                Resolución Ministerial N° 168/23
            </p>
            <p class="meta">C.U.E.: 1000770-00</p>
            <h1>{{ $alumno->nombre }}</h1>
            <p class="meta">
                DNI: {{ $alumno->dni ?? '—' }} ·
                Nivel: {{ $alumno->nivel->value }} ·
                Grado: {{ $alumno->grado }} ·
                División: {{ $alumno->division ?? '—' }} ·
                Turno: {{ $alumno->turno === \App\Alumnos\Models\Enums\Turno::Manana ? 'M' : 'T' }} ·
                Ciclo lectivo: {{ $periodo->nombre }}
            </p>

            <h2>
                {{ $trimestre->etiqueta() }}
            </h2>

            @foreach (($plantilla->estructura_campos['secciones'] ?? []) as $seccion)
                @php
                    $tipoSeccion = $seccion['tipo'] ?? null;

                    // campo_calculado nunca se guarda en datos -- se recalcula siempre a
                    // partir de otra sección (ej. Promedio de Materias, a partir de las
                    // notas finales de espacios_curriculares), solo en el trimestre dueño.
                    if ($tipoSeccion === 'campo_calculado') {
                        if (($seccion['momento'] ?? null) !== "trimestre_{$trimestre->trimestre}") {
                            $valor = null;
                        } else {
                            $filas = data_get($trimestre->datos, $seccion['fuente']['seccion']) ?? [];
                            $numeros = collect($filas)
                                ->pluck($seccion['fuente']['columna'])
                                ->filter(fn ($v) => is_numeric($v))
                                ->map(fn ($v) => (float) $v);
                            $valor = $numeros->isNotEmpty() ? round($numeros->avg(), 1) : null;
                        }
                        $visible = $valor !== null;
                    } else {
                        $valor = data_get($trimestre->datos, $seccion['id']);

                        // Consideraciones Conceptuales y Espacios Pendientes de Acreditación
                        // van siempre que la sección aplique a este trimestre, aunque hayan
                        // quedado vacías -- se manda el recuadro vacío en vez de omitirlo.
                        $siempreVisible = in_array($tipoSeccion, ['campo_texto', 'tabla_materias_libre'], true);
                        $existe = array_key_exists($seccion['id'], $trimestre->datos ?? []);
                        $visible = $existe && ($siempreVisible || ($valor !== null && $valor !== []));
                    }
                @endphp

                @if ($visible)
                    @if (($seccion['tipo'] ?? null) === 'campo_seleccion' && isset($seccion['opciones']))
                        {{-- Campo de una sola opción elegida (ej. Trayectoria Escolar, Promoción,
                        Promovido/a): se imprime como leyenda con todas las opciones posibles,
                        marcando la elegida, igual que en el formulario papel original. --}}
                        <p class="leyenda-seleccion">
                            <strong>{{ $seccion['titulo'] ?? $seccion['id'] }}:</strong>
                            @foreach ($seccion['opciones'] as $opcion)
                                <span @class(['opcion-elegida' => $opcion === $valor])>{{ $opcion }}</span>{{ ! $loop->last ? ' / ' : '' }}
                            @endforeach
                        </p>
                    @elseif (($seccion['tipo'] ?? null) === 'campo_calculado')
                        <p class="campo-calculado"><strong>{{ $seccion['titulo'] ?? $seccion['id'] }}:</strong> {{ $valor }}</p>
                    @else
                        <h3>{{ $seccion['titulo'] ?? $seccion['id'] }}</h3>
                        @include('boletines.partials.valor-generico', ['valor' => $valor, 'seccion' => $seccion])
                    @endif
                @endif
            @endforeach
        </div>
    @endforeach
</body>
</html>
