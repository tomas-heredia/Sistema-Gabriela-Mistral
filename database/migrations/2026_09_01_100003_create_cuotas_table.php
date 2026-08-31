<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->restrictOnDelete();
            $table->foreignId('periodo_lectivo_id')->constrained('periodos_lectivos')->restrictOnDelete();
            $table->string('tipo');
            // 0 = no aplica (matrícula), 1-12 = mes real (mensualidad).
            // No se usa NULL: MySQL no lo compara como igual a sí mismo en
            // un índice único, así que no protegería contra matrículas duplicadas.
            $table->unsignedTinyInteger('mes')->default(0);
            $table->unsignedBigInteger('monto_base');
            $table->string('descuento_tipo')->default('ninguno');
            $table->unsignedBigInteger('descuento_monto')->default(0);
            $table->unsignedBigInteger('monto');
            $table->date('fecha_vencimiento');
            $table->string('estado')->default('pendiente');
            $table->timestamps();

            $table->unique(['alumno_id', 'periodo_lectivo_id', 'tipo', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuotas');
    }
};
