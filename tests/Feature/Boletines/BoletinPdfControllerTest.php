<?php

use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Models\Enums\EstadoTrimestre;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');
});

function trimestreEnviadoConPdf(): BoletinTrimestre
{
    $trimestre = BoletinTrimestre::factory()->create(['estado' => EstadoTrimestre::Enviado]);

    $rutaPdf = "boletines/generados/{$trimestre->boletin_id}/trimestre-{$trimestre->trimestre}.pdf";
    Storage::disk('local')->put($rutaPdf, '%PDF-1.4 contenido de prueba');
    $trimestre->forceFill(['pdf_path' => $rutaPdf])->save();

    return $trimestre->fresh();
}

test('un cobrador puede descargar el pdf de un trimestre enviado', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $trimestre = trimestreEnviadoConPdf();

    $response = $this->actingAs($cobrador)->get(route('boletines.trimestres.pdf.descargar', $trimestre));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('un profesor sin el permiso de cargar boletines no puede descargar el pdf', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    $trimestre = trimestreEnviadoConPdf();

    $this->actingAs($profesor)->get(route('boletines.trimestres.pdf.descargar', $trimestre))->assertForbidden();
});

test('un trimestre sin pdf generado todavia devuelve 404 en vez de un archivo vacio', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $trimestre = BoletinTrimestre::factory()->create(['estado' => EstadoTrimestre::Cargado, 'pdf_path' => null]);

    $this->actingAs($cobrador)->get(route('boletines.trimestres.pdf.descargar', $trimestre))->assertNotFound();
});

test('un profesor con el permiso de cargar boletines puede descargar el pdf', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    $profesor->givePermissionTo('cargar_boletines');
    $trimestre = trimestreEnviadoConPdf();

    $this->actingAs($profesor)->get(route('boletines.trimestres.pdf.descargar', $trimestre))->assertOk();
});
