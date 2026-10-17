<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo real de BUON PREZZO: las categorías y productos del sistema Bean, exportados
 * a database/data/catalogo_bean.json (las fotos van aparte, ver load_product_images).
 * Reemplaza al catálogo de prueba (BP-####): esos productos y sus categorías vacías se
 * dan de baja con soft delete, porque puede haber ventas/compras apuntándolos.
 * El stock arranca en 0 (el de Bean es de otra tienda): se carga con un Almacén.
 * Los productos que ya existan por código no se tocan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $catalogo = json_decode(file_get_contents(database_path('data/catalogo_bean.json')), true);
        $ahora = now();

        DB::transaction(function () use ($catalogo, $ahora) {
            DB::table('productos')->where('codigo', 'like', 'BP-%')->whereNull('deleted_at')->update(['deleted_at' => $ahora]);

            $categorias = [];
            foreach ($catalogo['categorias'] as $categoria) {
                $existente = DB::table('categorias')->where('nombre', $categoria['nombre'])->orderByRaw('deleted_at is not null')->first(['id']);
                if ($existente) {
                    DB::table('categorias')->where('id', $existente->id)->update(['deleted_at' => null]);
                    $categorias[$categoria['nombre']] = $existente->id;
                } else {
                    $categorias[$categoria['nombre']] = DB::table('categorias')->insertGetId([
                        'nombre' => $categoria['nombre'],
                        'color' => $categoria['color'] ?: 'primary',
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]);
                }
            }

            $existentes = DB::table('productos')->whereNull('deleted_at')->pluck('codigo')->flip();
            $nuevos = collect($catalogo['productos'])
                ->reject(fn ($p) => isset($existentes[$p['codigo']]))
                ->map(fn ($p) => [
                    'codigo' => $p['codigo'],
                    'codigo_barras' => $p['codigo_barras'],
                    'nombre' => $p['nombre'],
                    'categoria' => $p['categoria'],
                    'categoria_id' => $categorias[$p['categoria']] ?? null,
                    'unidad' => $p['unidad'],
                    'precio_compra' => $p['precio_compra'],
                    'precio_venta' => $p['precio_venta'],
                    'stock_inicial' => 0,
                    'foto' => null,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            foreach ($nuevos->chunk(100) as $bloque) {
                DB::table('productos')->insert($bloque->values()->all());
            }

            // Categorías del catálogo de prueba que quedaron sin productos.
            DB::table('categorias')->whereNull('deleted_at')
                ->whereNotIn('nombre', array_keys($categorias))
                ->whereNotExists(fn ($q) => $q->from('productos')->whereColumn('productos.categoria_id', 'categorias.id')->whereNull('productos.deleted_at'))
                ->update(['deleted_at' => $ahora]);
        });
    }

    public function down(): void
    {
        // No se revierte: el catálogo real puede tener ventas, compras y fotos asociadas.
    }
};
