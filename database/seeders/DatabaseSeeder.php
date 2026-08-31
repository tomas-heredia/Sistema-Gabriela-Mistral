<?php

namespace Database\Seeders;

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Contrato;
use App\Alumnos\Models\Enums\Nivel;
use App\Alumnos\Models\Tutor;
use App\Boletines\Services\CreadorDeBoletines;
use App\Cobranzas\Models\Arancel;
use App\Cobranzas\Services\GeneradorDeCuotas;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use App\Sueldos\Models\ReciboSueldo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])->assignRole('administrador');

        $periodo = PeriodoLectivo::factory()->activo()->create([
            'nombre' => '2026',
            'fecha_inicio' => '2026-03-01',
            'fecha_fin' => '2026-12-15',
            'descuento_hermanos_pct' => 15,
        ]);

        foreach (Nivel::cases() as $nivel) {
            Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $nivel]);
            Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $nivel]);
        }

        $this->call(PlantillaBoletinSeeder::class);

        $generadorDeCuotas = app(GeneradorDeCuotas::class);
        $creadorDeBoletines = app(CreadorDeBoletines::class);

        Alumno::factory(10)
            ->primario()
            ->create()
            ->each(function (Alumno $alumno) use ($periodo, $generadorDeCuotas, $creadorDeBoletines) {
                $tutor = Tutor::factory()->create();
                $alumno->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);

                Contrato::factory()->create([
                    'alumno_id' => $alumno->id,
                    'periodo_lectivo_id' => $periodo->id,
                    'cargado_por_id' => User::first()->id,
                ]);

                $generadorDeCuotas->generar($alumno, $periodo);
                $creadorDeBoletines->crear($alumno, $periodo);
            });

        Alumno::factory(10)
            ->secundario()
            ->create()
            ->each(function (Alumno $alumno) use ($periodo, $generadorDeCuotas, $creadorDeBoletines) {
                $tutor = Tutor::factory()->create();
                $alumno->tutores()->attach($tutor, ['vinculo' => 'padre', 'responsable_pago' => true]);

                Contrato::factory()->create([
                    'alumno_id' => $alumno->id,
                    'periodo_lectivo_id' => $periodo->id,
                    'cargado_por_id' => User::first()->id,
                ]);

                $generadorDeCuotas->generar($alumno, $periodo);
                $creadorDeBoletines->crear($alumno, $periodo);
            });

        $administrador = User::first();

        User::factory(3)
            ->create()
            ->each(function (User $profesor) use ($administrador) {
                $profesor->assignRole('profesor');

                ReciboSueldo::factory()->create([
                    'profesor_id' => $profesor->id,
                    'cargado_por_id' => $administrador->id,
                ]);
            });
    }
}
