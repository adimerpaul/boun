<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Margen por porcentaje: precio de venta = costo + %. Se aplica por bloque (todas
 * las líneas de una compra, o los productos de una categoría / selección) y se
 * puede desactivar desde Configuración; desactivado, el precio de venta se carga a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->boolean('margen_activo')->default(true)->after('logo');
            $table->decimal('margen_porcentaje', 6, 2)->default(30)->after('margen_activo');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->dropColumn(['margen_activo', 'margen_porcentaje']);
        });
    }
};
