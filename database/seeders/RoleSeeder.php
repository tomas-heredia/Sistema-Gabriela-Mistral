<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Roles definidos en CLAUDE.md: administrador (acceso total),
     * cobrador (cobranzas y avisos), profesor (solo sus propios recibos de
     * sueldo), docente (solo cargar y enviar libretas).
     */
    public function run(): void
    {
        foreach (['administrador', 'cobrador', 'profesor', 'docente'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
