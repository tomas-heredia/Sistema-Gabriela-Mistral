<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_cuota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->restrictOnDelete();
            $table->foreignId('cuota_id')->constrained('cuotas')->restrictOnDelete();
            $table->unsignedBigInteger('monto_aplicado');
            $table->timestamps();

            $table->unique(['pago_id', 'cuota_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_cuota');
    }
};
