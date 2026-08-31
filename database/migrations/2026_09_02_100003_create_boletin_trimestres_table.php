<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boletin_trimestres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boletin_id')->constrained('boletines')->restrictOnDelete();
            // 1/2/3 = trimestres regulares. 4 = Etapa de Apoyo (solo
            // primaria, no se crea automático con el boletín).
            $table->unsignedTinyInteger('trimestre');
            $table->json('datos')->nullable();
            $table->string('estado')->default('pendiente');
            $table->foreignId('cargado_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('fecha_enviado')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->unique(['boletin_id', 'trimestre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boletin_trimestres');
    }
};
