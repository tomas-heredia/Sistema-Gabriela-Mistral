<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_saldo_a_favor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_id')->constrained('tutores')->restrictOnDelete();
            // El pago que generó o consumió este movimiento -- un pago
            // anulado se excluye del cálculo del saldo disponible (igual
            // criterio que Cuota::montoPagado() con pagos anulados), nunca
            // se borra el movimiento en sí (regla de CLAUDE.md: nada de
            // dinero se edita ni se borra).
            $table->foreignId('pago_id')->constrained('pagos')->restrictOnDelete();
            // Positivo: el tutor dejó saldo a favor en este pago.
            // Negativo: el tutor consumió saldo a favor existente en este pago.
            $table->bigInteger('monto');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_saldo_a_favor');
    }
};
