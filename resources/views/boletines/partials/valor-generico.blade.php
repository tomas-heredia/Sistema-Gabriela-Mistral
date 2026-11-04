{{--
    Renderiza cualquier valor de `datos` (json guardado por
    BoletinTrimestre::cargar) sin asumir la forma exacta de cada tipo de
    sección: escalar, lista de filas (array de arrays), o mapa clave→valor.
    Es el layout genérico que decidimos usar en vez de imitar el diseño de
    cada escaneo original — ver plan de implementación de Boletines.

    `$seccion` (opcional) es la definición de la sección en estructura_campos
    -- se usa solo para traducir la clave cruda de cada columna ("trimestre_1",
    "nombre") al nombre que ve el cobrador ("Primer Trimestre", o la etiqueta
    configurable de la columna libre vía `etiqueta_nombre`, ej. "Número de
    Orden" en Espacios Pendientes de Acreditación). Sin `$seccion` (ej. en la
    recursión del mapa simple de abajo) se cae al id crudo como antes.
--}}
@php
    $seccion ??= [];
    $esListaDeFilas = is_array($valor) && array_is_list($valor) && count($valor) > 0 && is_array($valor[0] ?? null);
    $esGrillaVacia = is_array($valor) && array_is_list($valor) && count($valor) === 0 && isset($seccion['columnas']);
    $esMapaSimple = is_array($valor) && ! array_is_list($valor);

    $etiquetaColumna = function (string $columna) use ($seccion) {
        if ($columna === 'nombre') {
            return $seccion['etiqueta_nombre'] ?? 'Nombre';
        }

        return collect($seccion['columnas'] ?? [])->firstWhere('id', $columna)['nombre'] ?? $columna;
    };

    // Para una escala de letras (S/AV/N, etc.) se imprime la letra y su
    // significado completo -- igual que ya se le muestra al cobrador en el
    // formulario (Cargar::etiquetaOpcion()), para no obligar a memorizar siglas.
    $etiquetaCelda = function ($valorCelda) use ($seccion) {
        $opciones = $seccion['escala']['opciones'] ?? [];
        $leyenda = $seccion['escala']['leyenda'] ?? null;

        if (! $leyenda || ! is_string($valorCelda)) {
            return $valorCelda;
        }

        $significados = array_map('trim', explode('/', $leyenda));
        $indice = array_search($valorCelda, $opciones, true);

        if ($indice === false || ! isset($significados[$indice])) {
            return $valorCelda;
        }

        return "{$valorCelda} — {$significados[$indice]}";
    };
@endphp

@if ($esListaDeFilas)
    @php $columnas = collect($valor)->flatMap(fn ($fila) => array_keys($fila))->unique()->values(); @endphp
    <table>
        <thead>
            <tr>
                @foreach ($columnas as $columna)
                    <th>{{ $etiquetaColumna($columna) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($valor as $fila)
                <tr>
                    @foreach ($columnas as $columna)
                        <td>{{ $etiquetaCelda(data_get($fila, $columna)) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@elseif ($esGrillaVacia)
    {{-- Sección aplicable a este trimestre pero sin filas cargadas (ej.
    Espacios Pendientes de Acreditación sin materias pendientes) -- se manda
    el recuadro vacío con los encabezados en vez de omitir la sección. --}}
    <table>
        <thead>
            <tr>
                <th>{{ $seccion['etiqueta_nombre'] ?? 'Nombre' }}</th>
                @foreach ($seccion['columnas'] as $columna)
                    <th>{{ $columna['nombre'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="{{ count($seccion['columnas']) + 1 }}">—</td>
            </tr>
        </tbody>
    </table>
@elseif ($esMapaSimple)
    <table>
        <tbody>
            @foreach ($valor as $clave => $subvalor)
                <tr>
                    <th>{{ $etiquetaColumna($clave) }}</th>
                    <td>
                        @if (is_array($subvalor))
                            @include('boletines.partials.valor-generico', ['valor' => $subvalor])
                        @else
                            {{ $etiquetaCelda($subvalor) }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@elseif (is_array($valor))
    {{-- Lista o mapa vacío (ej. una sección que quedó sin cargar) -- nada que mostrar. --}}
@else
    <p>{{ $valor }}</p>
@endif
