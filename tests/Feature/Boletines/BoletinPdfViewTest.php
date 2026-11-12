<?php

use App\Alumnos\Models\Alumno;
use App\Boletines\Models\Boletin;
use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Models\PlantillaBoletin;
use App\Core\Models\PeriodoLectivo;

/**
 * Un campo_seleccion (Trayectoria Escolar, Promoción, Promovido/a) imprime
 * solo la opción elegida -- nunca las demás, a diferencia del formulario
 * papel original que las listaba todas marcando la elegida.
 */
test('un campo_seleccion imprime solo la opcion elegida, nunca las demas', function () {
    $alumno = Alumno::factory()->primario()->create();
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $plantilla = PlantillaBoletin::factory()->create([
        'estructura_campos' => [
            'secciones' => [
                [
                    'id' => 'trayectoria_escolar',
                    'titulo' => 'Trayectoria Escolar',
                    'tipo' => 'campo_seleccion',
                    'momento' => 'trimestre_3',
                    'opciones' => ['Sostenida', 'Intermitente', 'De baja intensidad'],
                ],
            ],
        ],
    ]);
    $trimestre = BoletinTrimestre::factory()->create([
        'trimestre' => 3,
        'datos' => ['trayectoria_escolar' => 'Intermitente'],
    ]);

    $html = view('boletines.pdf', [
        'alumno' => $alumno,
        'plantilla' => $plantilla,
        'periodo' => $periodo,
        'trimestres' => collect([$trimestre]),
    ])->render();

    expect($html)
        ->toContain('<strong>Trayectoria Escolar:</strong>')
        ->toContain('<span class="opcion-elegida">Intermitente</span>')
        ->not->toContain('Sostenida')
        ->not->toContain('De baja intensidad');
});

/**
 * Si el campo_seleccion quedó sin elegir (valor en blanco), no se imprime
 * una leyenda vacía -- la sección simplemente no se muestra.
 */
test('un campo_seleccion sin elegir no imprime una leyenda vacia', function () {
    $alumno = Alumno::factory()->primario()->create();
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $plantilla = PlantillaBoletin::factory()->create([
        'estructura_campos' => [
            'secciones' => [
                [
                    'id' => 'trayectoria_escolar',
                    'titulo' => 'Trayectoria Escolar',
                    'tipo' => 'campo_seleccion',
                    'momento' => 'trimestre_3',
                    'opciones' => ['Sostenida', 'Intermitente', 'De baja intensidad'],
                ],
            ],
        ],
    ]);
    $trimestre = BoletinTrimestre::factory()->create([
        'trimestre' => 3,
        'datos' => ['trayectoria_escolar' => ''],
    ]);

    $html = view('boletines.pdf', [
        'alumno' => $alumno,
        'plantilla' => $plantilla,
        'periodo' => $periodo,
        'trimestres' => collect([$trimestre]),
    ])->render();

    expect($html)->not->toContain('Trayectoria Escolar');
});

/**
 * La orientación del ciclo orientado (único por ahora: "Cs. Soc. y Hum.")
 * se agrega al encabezado solo para 4to/5to/6to año -- ciclo básico
 * (1ro-3ro) y primaria no la tienen.
 */
test('el encabezado muestra la orientacion solo para secundaria ciclo orientado', function () {
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $plantilla = PlantillaBoletin::factory()->create();
    $trimestre = BoletinTrimestre::factory()->create(['trimestre' => 1, 'datos' => []]);

    $htmlOrientado = view('boletines.pdf', [
        'alumno' => Alumno::factory()->secundario()->create(['anio_secundaria' => 4]),
        'plantilla' => $plantilla,
        'periodo' => $periodo,
        'trimestres' => collect([$trimestre]),
    ])->render();

    $htmlBasico = view('boletines.pdf', [
        'alumno' => Alumno::factory()->secundario()->create(['anio_secundaria' => 2]),
        'plantilla' => $plantilla,
        'periodo' => $periodo,
        'trimestres' => collect([$trimestre]),
    ])->render();

    $htmlPrimaria = view('boletines.pdf', [
        'alumno' => Alumno::factory()->primario()->create(),
        'plantilla' => $plantilla,
        'periodo' => $periodo,
        'trimestres' => collect([$trimestre]),
    ])->render();

    expect($htmlOrientado)->toContain('Orientación: Cs. Soc. y Hum.');
    expect($htmlBasico)->not->toContain('Orientación:');
    expect($htmlPrimaria)->not->toContain('Orientación:');
});

/**
 * El título de cada trimestre usa BoletinTrimestre::etiqueta(), que es
 * nivel-aware: "PAeIP" en primaria, "ICAP" en secundaria, para las dos
 * instancias de recuperación.
 */
test('el titulo del trimestre usa PAeIP en primaria e ICAP en secundaria para la recuperacion', function () {
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $plantilla = PlantillaBoletin::factory()->create();

    $alumnoPrimario = Alumno::factory()->primario()->create();
    $boletinPrimario = Boletin::factory()->create(['alumno_id' => $alumnoPrimario->id, 'plantilla_id' => $plantilla->id]);
    $diciembrePrimaria = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletinPrimario->id,
        'trimestre' => BoletinTrimestre::TRIMESTRE_RECUPERACION_DICIEMBRE,
        'datos' => [],
    ]);

    $htmlPrimaria = view('boletines.pdf', [
        'alumno' => $alumnoPrimario,
        'plantilla' => $plantilla,
        'periodo' => $periodo,
        'trimestres' => collect([$diciembrePrimaria]),
    ])->render();

    $alumnoSecundario = Alumno::factory()->secundario()->create();
    $boletinSecundario = Boletin::factory()->create(['alumno_id' => $alumnoSecundario->id, 'plantilla_id' => $plantilla->id]);
    $diciembreSecundaria = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletinSecundario->id,
        'trimestre' => BoletinTrimestre::TRIMESTRE_RECUPERACION_DICIEMBRE,
        'datos' => [],
    ]);

    $htmlSecundaria = view('boletines.pdf', [
        'alumno' => $alumnoSecundario,
        'plantilla' => $plantilla,
        'periodo' => $periodo,
        'trimestres' => collect([$diciembreSecundaria]),
    ])->render();

    expect($htmlPrimaria)->toContain('Recuperación Diciembre (PAeIP)');
    expect($htmlSecundaria)->toContain('Recuperación Diciembre (ICAP)');
});

/**
 * "Promedio de Materias" (Libreta Final) pasó a ser un campo manual --
 * ya no se calcula solo a partir de las notas finales de cada materia, se
 * imprime tal cual se cargó a mano.
 */
test('promedio de materias ya no se calcula solo, se imprime el valor cargado a mano', function () {
    $alumno = Alumno::factory()->primario()->create();
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $plantilla = PlantillaBoletin::factory()->create([
        'estructura_campos' => ['secciones' => [
            [
                'id' => 'promedio_materias',
                'titulo' => 'Promedio de Materias',
                'tipo' => 'campo_numero',
                'momento' => 'trimestre_6',
                'acepta_coma' => true,
            ],
        ]],
    ]);
    $final = BoletinTrimestre::factory()->create([
        'trimestre' => BoletinTrimestre::TRIMESTRE_LIBRETA_FINAL,
        'datos' => [
            'promedio_materias' => '8.3',
            // El promedio real de estas dos notas sería 9 -- si el valor
            // cargado a mano (8.3) aparece tal cual, confirma que no se
            // está recalculando solo.
            'espacios_curriculares' => [
                ['nombre' => 'Lengua', 'nota_final' => '8'],
                ['nombre' => 'Matemática', 'nota_final' => '10'],
            ],
        ],
    ]);

    $html = view('boletines.pdf', [
        'alumno' => $alumno,
        'plantilla' => $plantilla,
        'periodo' => $periodo,
        'trimestres' => collect([$final]),
    ])->render();

    expect($html)->toContain('Promedio de Materias')
        ->toContain('<p>8.3</p>');
});

/**
 * Consideraciones Conceptuales (campo_texto) y Espacios Pendientes de
 * Acreditación (tabla_materias_libre) siempre se mandan en el PDF cuando
 * aplican a ese trimestre, aunque hayan quedado vacías -- se manda el
 * recuadro vacío en vez de omitir la sección entera.
 */
test('consideraciones conceptuales y espacios pendientes de acreditacion se mandan vacios, no se omiten', function () {
    $alumno = Alumno::factory()->secundario()->create();
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $plantilla = PlantillaBoletin::factory()->create([
        'estructura_campos' => ['secciones' => [
            [
                'id' => 'consideraciones_conceptuales_trimestre_1',
                'titulo' => 'Consideraciones Conceptuales',
                'tipo' => 'campo_texto',
                'momento' => 'trimestre_1',
            ],
            [
                'id' => 'espacios_pendientes_acreditacion',
                'titulo' => 'Espacios Pendientes de Acreditación',
                'tipo' => 'tabla_materias_libre',
                'etiqueta_nombre' => 'Número de Orden',
                'columnas' => [
                    ['id' => 'espacio_curricular', 'nombre' => 'Espacio Curricular'],
                    ['id' => 'calificacion', 'nombre' => 'Calificación'],
                ],
                'filas_libres' => true,
            ],
        ]],
    ]);
    $trimestre = BoletinTrimestre::factory()->create([
        'trimestre' => 1,
        'datos' => [
            'consideraciones_conceptuales_trimestre_1' => '',
            'espacios_pendientes_acreditacion' => [],
        ],
    ]);

    $html = view('boletines.pdf', [
        'alumno' => $alumno,
        'plantilla' => $plantilla,
        'periodo' => $periodo,
        'trimestres' => collect([$trimestre]),
    ])->render();

    expect($html)
        ->toContain('Consideraciones Conceptuales')
        ->toContain('Espacios Pendientes de Acreditación')
        ->toContain('Número de Orden')
        ->toContain('Espacio Curricular');
});

/**
 * Una sección que ni siquiera aplica a este trimestre (no quedó ninguna
 * clave en `datos` para ella) sigue sin mostrarse -- el "siempre visible"
 * de arriba es solo para cuando la sección SÍ aplica pero quedó vacía.
 */
test('una seccion que no aplica a este trimestre sigue sin mostrarse aunque sea campo_texto', function () {
    $alumno = Alumno::factory()->secundario()->create();
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $plantilla = PlantillaBoletin::factory()->create([
        'estructura_campos' => ['secciones' => [
            [
                'id' => 'consideraciones_conceptuales_trimestre_2',
                'titulo' => 'Consideraciones Conceptuales T2',
                'tipo' => 'campo_texto',
                'momento' => 'trimestre_2',
            ],
        ]],
    ]);
    $trimestre1 = BoletinTrimestre::factory()->create(['trimestre' => 1, 'datos' => []]);

    $html = view('boletines.pdf', [
        'alumno' => $alumno,
        'plantilla' => $plantilla,
        'periodo' => $periodo,
        'trimestres' => collect([$trimestre1]),
    ])->render();

    expect($html)->not->toContain('Consideraciones Conceptuales T2');
});

/**
 * Las apreciaciones (escala de letras) se imprimen con la letra y su
 * significado completo, no solo la letra cruda.
 */
test('una celda con escala de letras se imprime con la letra y su significado', function () {
    $alumno = Alumno::factory()->secundario()->create();
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $plantilla = PlantillaBoletin::factory()->create([
        'estructura_campos' => ['secciones' => [
            [
                'id' => 'apreciaciones',
                'titulo' => 'Apreciaciones',
                'tipo' => 'tabla_criterios',
                'escala' => ['tipo' => 'texto', 'opciones' => ['S', 'AV', 'N'], 'leyenda' => 'Siempre / A veces / Nunca'],
                'columnas' => [['id' => 'trimestre_1', 'nombre' => 'Trimestre 1']],
            ],
        ]],
    ]);
    $trimestre = BoletinTrimestre::factory()->create([
        'trimestre' => 1,
        'datos' => ['apreciaciones' => [['nombre' => 'Participa en clase', 'trimestre_1' => 'AV']]],
    ]);

    $html = view('boletines.pdf', [
        'alumno' => $alumno,
        'plantilla' => $plantilla,
        'periodo' => $periodo,
        'trimestres' => collect([$trimestre]),
    ])->render();

    expect($html)->toContain('AV — A veces')
        ->not->toContain('Pocas veces');
});

/**
 * El PDF es acumulativo: cada trimestre `enviado` repite el encabezado
 * completo en su propia página. "Ciclo lectivo" es el único dato de ese
 * encabezado que no debe repetirse -- sale solo en la página del primer
 * trimestre, el resto (DNI, Nivel, Grado/Curso, División, Turno) repite
 * en cada página como siempre.
 */
test('ciclo lectivo solo se imprime en la pagina del primer trimestre del pdf acumulado', function () {
    $alumno = Alumno::factory()->primario()->create();
    $periodo = PeriodoLectivo::factory()->activo()->create(['nombre' => '2026']);
    $plantilla = PlantillaBoletin::factory()->create(['estructura_campos' => ['secciones' => []]]);
    $trimestre1 = BoletinTrimestre::factory()->create(['trimestre' => 1, 'datos' => []]);
    $trimestre2 = BoletinTrimestre::factory()->create(['trimestre' => 2, 'datos' => []]);

    $html = view('boletines.pdf', [
        'alumno' => $alumno,
        'plantilla' => $plantilla,
        'periodo' => $periodo,
        'trimestres' => collect([$trimestre1, $trimestre2]),
    ])->render();

    expect(substr_count($html, 'Ciclo lectivo: 2026'))->toBe(1)
        ->and(substr_count($html, 'DNI:'))->toBe(2);
});

/**
 * "Curso" para secundaria, "Grado" para primaria -- mismo dato
 * ($alumno->grado), solo cambia la etiqueta.
 */
test('el encabezado dice Curso en secundaria y Grado en primaria', function () {
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $plantilla = PlantillaBoletin::factory()->create();
    $trimestre = BoletinTrimestre::factory()->create(['trimestre' => 1, 'datos' => []]);

    $htmlSecundaria = view('boletines.pdf', [
        'alumno' => Alumno::factory()->secundario()->create(),
        'plantilla' => $plantilla,
        'periodo' => $periodo,
        'trimestres' => collect([$trimestre]),
    ])->render();

    $htmlPrimaria = view('boletines.pdf', [
        'alumno' => Alumno::factory()->primario()->create(),
        'plantilla' => $plantilla,
        'periodo' => $periodo,
        'trimestres' => collect([$trimestre]),
    ])->render();

    expect($htmlSecundaria)->toContain('Curso:')->not->toContain('Grado:');
    expect($htmlPrimaria)->toContain('Grado:')->not->toContain('Curso:');
});

/**
 * El orden de las columnas de una tabla (Espacios Curriculares, Espacios
 * Pendientes de Acreditación) sale de `estructura_campos`, no de cómo haya
 * quedado guardada cada fila -- antes dependía de `array_keys()` sobre los
 * datos, así que una fila guardada con las claves en otro orden (ej. una
 * corrección que tocó una columna antes que otra) podía mostrar las
 * columnas en un orden distinto al declarado en la plantilla.
 */
test('el orden de las columnas de una tabla sigue la plantilla, no el orden en que quedaron guardadas las claves', function () {
    $alumno = Alumno::factory()->secundario()->create();
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $plantilla = PlantillaBoletin::factory()->create([
        'estructura_campos' => ['secciones' => [
            [
                'id' => 'espacios_pendientes_acreditacion',
                'titulo' => 'Espacios Pendientes de Acreditación',
                'tipo' => 'tabla_materias_libre',
                'etiqueta_nombre' => 'Número de Orden',
                'columnas' => [
                    ['id' => 'espacio_curricular', 'nombre' => 'Espacio Curricular'],
                    ['id' => 'calificacion', 'nombre' => 'Calificación'],
                    ['id' => 'fecha', 'nombre' => 'Fecha'],
                ],
                'filas_libres' => true,
            ],
        ]],
    ]);
    // Fila guardada con las claves en un orden distinto al declarado --
    // simula una corrección que completó "fecha" antes que "calificacion".
    $trimestre = BoletinTrimestre::factory()->create([
        'trimestre' => 1,
        'datos' => ['espacios_pendientes_acreditacion' => [
            ['fecha' => '2026-11-01', 'nombre' => '1', 'espacio_curricular' => 'Matemática', 'calificacion' => '5'],
        ]],
    ]);

    $html = view('boletines.pdf', [
        'alumno' => $alumno,
        'plantilla' => $plantilla,
        'periodo' => $periodo,
        'trimestres' => collect([$trimestre]),
    ])->render();

    $posicionOrden = strpos($html, 'Número de Orden');
    $posicionEspacio = strpos($html, 'Espacio Curricular');
    $posicionCalificacion = strpos($html, '>Calificación<');
    $posicionFecha = strpos($html, '>Fecha<');

    expect($posicionOrden)->toBeLessThan($posicionEspacio)
        ->and($posicionEspacio)->toBeLessThan($posicionCalificacion)
        ->and($posicionCalificacion)->toBeLessThan($posicionFecha);
});
