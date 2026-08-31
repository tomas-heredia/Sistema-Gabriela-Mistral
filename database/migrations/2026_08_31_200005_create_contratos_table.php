<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->foreignId('periodo_lectivo_id')->constrained('periodos_lectivos')->restrictOnDelete();
            $table->string('archivo');
            $table->date('fecha');
            $table->foreignId('cargado_por_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['alumno_id', 'periodo_lectivo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
