<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * El rol administra_alumnos desaparece: su única capacidad (cargar y
 * enviar libretas) pasa a ser un permiso opcional sobre el rol profesor.
 * Cualquier usuario que ya tuviera administra_alumnos en producción pasa a
 * profesor con ese permiso, para no perder acceso de un día para el otro.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permiso = Permission::firstOrCreate(['name' => 'cargar_boletines', 'guard_name' => 'web']);
        $rolAdministraAlumnos = Role::where('name', 'administra_alumnos')->where('guard_name', 'web')->first();

        if ($rolAdministraAlumnos) {
            Role::firstOrCreate(['name' => 'profesor', 'guard_name' => 'web']);

            $rolAdministraAlumnos->users()->get()->each(function ($usuario) use ($permiso) {
                $usuario->syncRoles(['profesor']);
                $usuario->givePermissionTo($permiso);
            });

            $rolAdministraAlumnos->delete();
        }
    }

    public function down(): void
    {
        Role::firstOrCreate(['name' => 'administra_alumnos', 'guard_name' => 'web']);
    }
};
