<?php

use App\Alumnos\Models\Alumno;
use App\Boletines\Models\Boletin;
use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Models\PlantillaBoletin;
use App\Core\Models\PeriodoLectivo;

/**
 * Un campo_seleccion (Trayectoria Escolar, Promoción, Promovido/a) se
 * imprime como leyenda con todas las opciones posibles, marcando la
 * elegida -- igual que el formulario papel original -- en vez de mostrar
 * solo el valor elegido como el resto de los campos de texto/número.
 */
test('un campo_seleccion se imprime como leyenda con todas las opciones, marcando la elegida', function () {
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
        ->toContain('Sostenida')
        ->toContain('De baja intensidad')
        ->toContain('<span class="opcion-elegida">Intermitente</span>')
        ->not->toContain('<span class="opcion-elegida">Sostenida</span>');
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
 * "Promedio de Materias" (Libreta Final) nunca se guarda en datos -- se
 * imprime como texto simple, recalculado a partir de la columna que
 * declara `fuente`, y solo en el trimestre al que pertenece (momento).
 */
test('un campo_calculado se imprime como texto con el promedio, solo en su propio trimestre', function () {
    $alumno = Alumno::factory()->primario()->create();
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $plantilla = PlantillaBoletin::factory()->create([
        'estructura_campos' => ['secciones' => [
            [
                'id' => 'promedio_materias',
                'titulo' => 'Promedio de Materias',
                'tipo' => 'campo_calculado',
                'momento' => 'trimestre_6',
                'fuente' => ['seccion' => 'espacios_curriculares', 'columna' => 'nota_final'],
            ],
        ]],
    ]);
    $trimestre1 = BoletinTrimestre::factory()->create([
        'trimestre' => 1,
        'datos' => ['espacios_curriculares' => [['nombre' => 'Lengua', 'trimestre_1' => '7']]],
    ]);
    $final = BoletinTrimestre::factory()->create([
        'trimestre' => BoletinTrimestre::TRIMESTRE_LIBRETA_FINAL,
        'datos' => ['espacios_curriculares' => [
            ['nombre' => 'Lengua', 'nota_final' => '8'],
            ['nombre' => 'Matemática', 'nota_final' => '10'],
        ]],
    ]);

    $html = view('boletines.pdf', [
        'alumno' => $alumno,
        'plantilla' => $plantilla,
        'periodo' => $periodo,
        'trimestres' => collect([$trimestre1, $final]),
    ])->render();

    expect($html)->toContain('Promedio de Materias:</strong> 9');
    expect(substr_count($html, 'Promedio de Materias'))->toBe(1);
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
