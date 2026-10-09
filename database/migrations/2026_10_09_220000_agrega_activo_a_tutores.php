<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mismo criterio que Alumno: "eliminar" un tutor pasa a desactivarlo, no a
 * borrarlo -- conserva el historial de pagos/alumnos ya asociado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutores', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('correo');
        });
    }

    public function down(): void
    {
        Schema::table('tutores', function (Blueprint $table) {
            $table->dropColumn('activo');
        });
    }
};
