<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Roles definidos en CLAUDE.md: administrador (acceso total),
     * cobrador (cobranzas y avisos), profesor (sus propios recibos de
     * sueldo, y opcionalmente carga de libretas vía el permiso
     * 'cargar_boletines' -- ver RoleSeeder::class y BoletinPolicy),
     * director_secretario (sus propios recibos de sueldo, y ver/cargar
     * libretas con la misma capacidad de administrador de corregir un
     * trimestre ya enviado -- sin necesitar el permiso 'cargar_boletines').
     */
    public function run(): void
    {
        foreach (['administrador', 'cobrador', 'profesor', 'director_secretario'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        Permission::firstOrCreate(['name' => 'cargar_boletines', 'guard_name' => 'web']);
    }
}
