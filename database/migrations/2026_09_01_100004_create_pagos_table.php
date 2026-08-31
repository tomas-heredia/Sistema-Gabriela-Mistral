<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_id')->constrained('tutores')->restrictOnDelete();
            $table->unsignedBigInteger('monto');
            $table->string('medio_pago');
            $table->date('fecha');
            $table->string('numero_recibo')->unique();
            $table->foreignId('cobrador_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('motivo_anulacion')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
