<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibo_sueldo_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recibo_sueldo_id')->constrained('recibos_sueldo')->cascadeOnDelete();
            $table->string('archivo');
            $table->timestamps();
        });

        DB::table('recibos_sueldo')->orderBy('id')->select('id', 'archivo')->get()->each(function ($recibo) {
            DB::table('recibo_sueldo_archivos')->insert([
                'recibo_sueldo_id' => $recibo->id,
                'archivo' => $recibo->archivo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('recibos_sueldo', function (Blueprint $table) {
            $table->dropColumn('archivo');
        });
    }

    public function down(): void
    {
        Schema::table('recibos_sueldo', function (Blueprint $table) {
            $table->string('archivo')->nullable()->after('periodo');
        });

        DB::table('recibo_sueldo_archivos')->orderBy('id')->get()->groupBy('recibo_sueldo_id')->each(function ($archivos, $reciboSueldoId) {
            DB::table('recibos_sueldo')
                ->where('id', $reciboSueldoId)
                ->update(['archivo' => $archivos->first()->archivo]);
        });

        Schema::dropIfExists('recibo_sueldo_archivos');
    }
};
