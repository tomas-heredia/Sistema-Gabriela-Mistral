<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Roles definidos en CLAUDE.md: administrador (acceso total),
     * cobrador (cobranzas y avisos), profesor (boletines y sus propios recibos).
     */
    public function run(): void
    {
        foreach (['administrador', 'cobrador', 'profesor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
