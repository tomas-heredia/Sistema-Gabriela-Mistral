<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones_mora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_id')->constrained('tutores')->restrictOnDelete();
            $table->unsignedBigInteger('monto_adeudado');
            $table->unsignedSmallInteger('meses_adeudados');
            $table->string('estado')->default('pendiente');
            $table->date('fecha');
            $table->timestamp('fecha_envio')->nullable();
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones_mora');
    }
};
