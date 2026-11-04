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
