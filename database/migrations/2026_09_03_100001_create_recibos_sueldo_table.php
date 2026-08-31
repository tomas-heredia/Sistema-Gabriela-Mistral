<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibos_sueldo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profesor_id')->constrained('users')->restrictOnDelete();
            $table->string('periodo');
            $table->string('archivo');
            $table->foreignId('cargado_por_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha_carga');
            $table->timestamps();

            $table->unique(['profesor_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recibos_sueldo');
    }
};
