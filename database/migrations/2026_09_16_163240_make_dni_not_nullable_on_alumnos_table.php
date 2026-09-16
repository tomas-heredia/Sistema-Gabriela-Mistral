<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El DNI del alumno deja de ser opcional. Si ya existen alumnos con el DNI
 * vacío, esta migración falla a propósito en vez de inventar un valor --
 * hay que completarlo a mano antes de poder aplicarla (mismo criterio que
 * 2026_09_07_150000_make_correo_not_nullable_on_tutores_table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->string('dni')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->string('dni')->nullable()->change();
        });
    }
};
