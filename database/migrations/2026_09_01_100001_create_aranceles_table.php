<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aranceles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodo_lectivo_id')->constrained('periodos_lectivos')->restrictOnDelete();
            $table->string('nivel');
            $table->string('tipo');
            $table->unsignedBigInteger('monto');
            $table->timestamps();

            $table->unique(['periodo_lectivo_id', 'nivel', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aranceles');
    }
};
