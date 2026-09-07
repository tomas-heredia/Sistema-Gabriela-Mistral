<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El correo del tutor deja de ser opcional: sin él, ni el envío de
 * libretas ni los avisos de mora tienen forma de llegar a destino. Si ya
 * existen tutores con el correo vacío, esta migración falla a propósito
 * en vez de inventar un valor — hay que completar ese dato a mano antes
 * de poder aplicarla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutores', function (Blueprint $table) {
            $table->string('correo')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tutores', function (Blueprint $table) {
            $table->string('correo')->nullable()->change();
        });
    }
};
