<?php

use App\Alumnos\Models\Alumno;
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
