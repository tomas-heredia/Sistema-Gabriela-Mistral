<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Eliminar" un usuario (cualquier rol) pasa a desactivarlo, no a borrarlo
 * -- conserva quién cargó qué (pagos, boletines, recibos de sueldo) en vez
 * de dejar esas referencias huérfanas o bloqueadas por la FK. Un usuario
 * inactivo no puede loguearse (ver LoginForm::authenticate()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('activo');
        });
    }
};
