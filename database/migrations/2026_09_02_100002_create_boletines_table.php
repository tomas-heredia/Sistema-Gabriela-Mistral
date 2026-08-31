<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boletines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->restrictOnDelete();
            $table->foreignId('plantilla_id')->constrained('plantillas_boletin')->restrictOnDelete();
            $table->foreignId('periodo_lectivo_id')->constrained('periodos_lectivos')->restrictOnDelete();
            $table->string('estado')->default('en_curso');
            $table->timestamps();

            $table->unique(['alumno_id', 'periodo_lectivo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boletines');
    }
};
