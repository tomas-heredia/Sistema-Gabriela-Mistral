<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El interés deja de cargarse como porcentaje (10 = 10%) y pasa a ser un
 * índice multiplicador (1.10 = 10%, 1.40 = 40%) -- más parecido a como lo
 * maneja el colegio en la práctica. 0% de interés ahora es índice 1 (el
 * monto no cambia), no 0 -- por eso la conversión es 1 + porcentaje/100,
 * nunca una simple renombrada de columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->decimal('interes_indice', 5, 2)->nullable()->after('medio_pago');
        });

        DB::table('pagos')->whereNotNull('interes_porcentaje')->update([
            'interes_indice' => DB::raw('1 + (interes_porcentaje / 100)'),
        ]);

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn('interes_porcentaje');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->decimal('interes_porcentaje', 5, 2)->nullable()->after('medio_pago');
        });

        DB::table('pagos')->whereNotNull('interes_indice')->update([
            'interes_porcentaje' => DB::raw('(interes_indice - 1) * 100'),
        ]);

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn('interes_indice');
        });
    }
};
