<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumno_tutor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->foreignId('tutor_id')->constrained('tutores')->cascadeOnDelete();
            $table->string('vinculo');
            $table->boolean('responsable_pago')->default(false);
            $table->timestamps();

            $table->unique(['alumno_id', 'tutor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumno_tutor');
    }
};
