<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El N° de recibo pasa de ser uno por operación de pago (`pagos.numero_recibo`)
 * a uno por cuota pagada (`pago_cuota.numero_recibo`) -- ahora se emite un
 * comprobante en PDF por cada mes, igual que se hacía con los talonarios de
 * papel (un recibo físico por mes, aunque se cobren varios juntos).
 *
 * El interés es un porcentaje que el cobrador carga una sola vez por
 * operación (`pagos.interes_porcentaje`), pero el monto que resulta de
 * aplicarlo depende de la deuda de cada cuota, así que el importe ya
 * calculado se guarda por separado en cada fila de `pago_cuota`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropUnique(['numero_recibo']);
            $table->dropColumn('numero_recibo');
            $table->decimal('interes_porcentaje', 5, 2)->nullable()->after('medio_pago');
        });

        Schema::table('pago_cuota', function (Blueprint $table) {
            $table->string('numero_recibo')->nullable()->unique()->after('cuota_id');
            $table->unsignedBigInteger('interes_aplicado')->default(0)->after('monto_aplicado');
            $table->string('pdf_path')->nullable()->after('interes_aplicado');
        });
    }

    public function down(): void
    {
        Schema::table('pago_cuota', function (Blueprint $table) {
            $table->dropColumn(['numero_recibo', 'interes_aplicado', 'pdf_path']);
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn('interes_porcentaje');
            $table->string('numero_recibo')->unique()->after('medio_pago');
        });
    }
};
