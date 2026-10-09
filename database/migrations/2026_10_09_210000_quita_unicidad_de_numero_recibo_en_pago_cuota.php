<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un pago que cubre varios meses del mismo alumno ahora genera un solo
 * comprobante que los junta a todos, en vez de uno por mes -- ver
 * AsignadorDePagos::aplicar(), que les asigna a esas filas de pago_cuota el
 * mismo numero_recibo. Deja de ser único por fila; sigue siendo único "por
 * grupo" (todas las filas de un mismo número comparten pago y alumno), pero
 * eso ya lo garantiza la asignación, no una restricción de la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago_cuota', function (Blueprint $table) {
            $table->dropUnique(['numero_recibo']);
        });
    }

    public function down(): void
    {
        Schema::table('pago_cuota', function (Blueprint $table) {
            $table->unique('numero_recibo');
        });
    }
};
