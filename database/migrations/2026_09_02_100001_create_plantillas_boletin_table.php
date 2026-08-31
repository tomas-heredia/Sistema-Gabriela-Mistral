<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_boletin', function (Blueprint $table) {
            $table->id();
            $table->string('nivel');
            $table->unsignedTinyInteger('anio')->nullable();
            $table->string('nombre');
            $table->string('archivo');
            $table->json('estructura_campos');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('reemplaza_a_id')->nullable()->constrained('plantillas_boletin')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->foreignId('creado_por_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_boletin');
    }
};
