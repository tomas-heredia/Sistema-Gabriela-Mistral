<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumnos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('dni')->nullable()->unique();
            $table->date('fecha_nacimiento');
            $table->string('nivel');
            $table->string('grado');
            $table->unsignedTinyInteger('anio_secundaria')->nullable();
            $table->string('division')->nullable();
            $table->string('libro_folio')->nullable();
            $table->string('turno');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumnos');
    }
};
