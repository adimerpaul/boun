<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Fotos de productos y logo de la empresa. Los archivos viven versionados en
 * database/data/imagenes (los de productos nombrados por código, porque los id
 * cambian entre bases) y se copian a public/images, que es lo que sirve la API.
 * Sólo se completan los productos sin foto o cuya foto no existe en el disco:
 * lo que ya se haya subido en el servidor no se toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        $origen = database_path('data/imagenes');

        foreach (File::glob($origen.'/productos/*.webp') as $archivo) {
            $codigo = pathinfo($archivo, PATHINFO_FILENAME);
            $producto = DB::table('productos')->whereNull('deleted_at')
                ->where(fn ($q) => $q->where('codigo', $codigo)->orWhere('codigo_barras', $codigo))
                ->first(['id', 'foto']);
            if (! $producto || $this->exists($producto->foto)) {
                continue;
            }
            $ruta = 'productos/'.$codigo.'.webp';
            $this->copy($archivo, $ruta);
            DB::table('productos')->where('id', $producto->id)->update(['foto' => $ruta]);
        }

        $logo = $origen.'/empresa/logo_buonprezzo.webp';
        $configuracion = DB::table('configuraciones')->first(['id', 'logo']);
        if (is_file($logo) && $configuracion && ! $this->exists($configuracion->logo)) {
            $this->copy($logo, 'empresa/logo_buonprezzo.webp');
            DB::table('configuraciones')->where('id', $configuracion->id)->update(['logo' => 'empresa/logo_buonprezzo.webp']);
        }
    }

    public function down(): void
    {
        // Las imágenes se quedan: pueden estar en uso por ventas/compras (snapshots de foto).
    }

    private function exists(?string $ruta): bool
    {
        return $ruta && is_file(public_path('images/'.$ruta));
    }

    private function copy(string $archivo, string $ruta): void
    {
        File::ensureDirectoryExists(dirname(public_path('images/'.$ruta)));
        File::copy($archivo, public_path('images/'.$ruta));
    }
};
