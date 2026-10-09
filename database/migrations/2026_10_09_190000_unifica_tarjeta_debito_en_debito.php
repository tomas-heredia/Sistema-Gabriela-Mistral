<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 'tarjeta_debito' se elimina del enum MedioPago -- quedaba redundante con
 * 'debito'. Cualquier pago ya guardado con ese valor pasa a 'debito' para
 * no romper al hidratar el enum (Pago::medio_pago lo castea a MedioPago,
 * que explota si encuentra un valor que ya no es un case válido).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pagos')->where('medio_pago', 'tarjeta_debito')->update(['medio_pago' => 'debito']);
    }

    public function down(): void {}
};
