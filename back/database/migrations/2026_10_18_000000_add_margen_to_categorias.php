<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Margen por categoría: si está activo, en una compra nueva el precio de venta de los
 * productos de esa categoría sigue a su costo + este porcentaje (en lugar del general
 * de Configuración).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->boolean('margen_activo')->default(false)->after('color');
            $table->decimal('margen_porcentaje', 6, 2)->default(0)->after('margen_activo');
        });
    }

    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropColumn(['margen_activo', 'margen_porcentaje']);
        });
    }
};
