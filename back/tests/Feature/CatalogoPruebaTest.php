<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoPruebaTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_is_the_bean_catalog_without_test_products(): void
    {
        $catalogo = json_decode(file_get_contents(database_path('data/catalogo_bean.json')), true);

        $this->assertSame(count($catalogo['productos']), Producto::count());
        $this->assertSame(count($catalogo['categorias']), Categoria::count());
        $this->assertSame(0, Producto::where('codigo', 'like', 'BP-%')->count());
        $this->assertSame(0, Producto::whereNull('categoria_id')->whereNotNull('categoria')->count());
        $this->assertGreaterThan(0, Producto::whereNotNull('foto')->count());
    }

    public function test_admin_can_login_and_receive_a_sanctum_token(): void
    {
        $this->postJson('/api/login', [
            'username' => 'admin',
            'password' => 'admin',
        ])->assertOk()
            ->assertJsonStructure(['token', 'user', 'must_change_password'])
            ->assertJsonPath('user.username', 'admin');
    }
}
