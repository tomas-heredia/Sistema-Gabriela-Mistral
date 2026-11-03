<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('boletin_trimestres', function (Blueprint $table) {
            $table->boolean('requiere_reenvio')->default(false)->after('pdf_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boletin_trimestres', function (Blueprint $table) {
            $table->dropColumn('requiere_reenvio');
        });
    }
};
