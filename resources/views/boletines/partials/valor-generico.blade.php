{{--
    Renderiza cualquier valor de `datos` (json guardado por
    BoletinTrimestre::cargar) sin asumir la forma exacta de cada tipo de
    sección: escalar, lista de filas (array de arrays), o mapa clave→valor.
    Es el layout genérico que decidimos usar en vez de imitar el diseño de
    cada escaneo original — ver plan de implementación de Boletines.
--}}
@php
    $esListaDeFilas = is_array($valor) && array_is_list($valor) && count($valor) > 0 && is_array($valor[0] ?? null);
    $esMapaSimple = is_array($valor) && ! array_is_list($valor);
@endphp

@if ($esListaDeFilas)
    @php $columnas = collect($valor)->flatMap(fn ($fila) => array_keys($fila))->unique()->values(); @endphp
    <table>
        <thead>
            <tr>
                @foreach ($columnas as $columna)
                    <th>{{ $columna }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($valor as $fila)
                <tr>
                    @foreach ($columnas as $columna)
                        <td>{{ data_get($fila, $columna) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@elseif ($esMapaSimple)
    <table>
        <tbody>
            @foreach ($valor as $clave => $subvalor)
                <tr>
                    <th>{{ $clave }}</th>
                    <td>
                        @if (is_array($subvalor))
                            @include('boletines.partials.valor-generico', ['valor' => $subvalor])
                        @else
                            {{ $subvalor }}
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
