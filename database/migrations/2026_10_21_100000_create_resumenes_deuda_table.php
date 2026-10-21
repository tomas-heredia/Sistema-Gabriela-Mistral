<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumenes_deuda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_id')->constrained('tutores')->restrictOnDelete();
            // 'YYYY-MM' -- identifica el envío mensual y, junto con tutor_id,
            // evita mandar el mismo resumen dos veces si el comando corre
            // más de una vez en el mes (reintento manual, scheduler que se
            // disparó dos veces, etc.).
            $table->string('periodo', 7);
            $table->unsignedBigInteger('monto_adeudado');
            $table->string('pdf_path');
            $table->timestamp('enviado_at');
            $table->timestamps();

            $table->unique(['tutor_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resumenes_deuda');
    }
};
