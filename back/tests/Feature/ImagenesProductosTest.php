<?php

namespace Tests\Feature;

use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImagenesProductosTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_copies_the_photo_of_a_product_without_one(): void
    {
        $archivo = collect(File::glob(database_path('data/imagenes/productos/*.webp')))->first();
        $this->assertNotNull($archivo, 'No hay fotos versionadas en database/data/imagenes/productos');
        $codigo = pathinfo($archivo, PATHINFO_FILENAME);
        $destino = public_path("images/productos/{$codigo}.webp");
        $existia = is_file($destino);

        $producto = Producto::firstOrFail();
        $producto->update(['codigo' => $codigo, 'foto' => 'productos/no-existe.webp']);
        $otro = Producto::where('id', '!=', $producto->id)->firstOrFail();
        $otro->update(['foto' => null]);

        (require database_path('migrations/2026_10_19_010000_load_product_images.php'))->up();

        $this->assertSame("productos/{$codigo}.webp", $producto->fresh()->foto);
        $this->assertFileExists($destino);
        $this->assertNull($otro->fresh()->foto);

        if (! $existia) {
            File::delete($destino);
        }
    }
}
