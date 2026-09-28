<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El descuento por hermanos deja de ser un porcentaje configurable por
 * período: ahora es una regla fija (beca completa para exactamente el 3er
 * hermano matriculado, sin importar cuántos sean en total -- ver
 * GeneradorDeCuotas::esElTercerHermano()). Pedido del cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodos_lectivos', function (Blueprint $table) {
            $table->dropColumn('descuento_hermanos_pct');
        });
    }

    public function down(): void
    {
        Schema::table('periodos_lectivos', function (Blueprint $table) {
            $table->decimal('descuento_hermanos_pct', 5, 2)->default(0);
        });
    }
};
