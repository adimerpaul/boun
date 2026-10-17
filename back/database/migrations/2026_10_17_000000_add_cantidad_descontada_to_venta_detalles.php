<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Venta sin stock: se permite vender más de lo que hay, pero el stock nunca baja de
 * cero. `cantidad_descontada` guarda lo que de verdad salió del stock (≤ cantidad)
 * para que la anulación devuelva sólo eso. NULL = venta anterior, se descontó todo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->decimal('cantidad_descontada', 12, 3)->nullable()->after('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropColumn('cantidad_descontada');
        });
    }
};
