<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MargenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_configuration_stores_the_margin_option(): void
    {
        $this->admin();

        $this->putJson('/api/configuracion', ['nombre_empresa' => 'BUON PREZZO', 'margen_activo' => false, 'margen_porcentaje' => 25])
            ->assertOk()
            ->assertJsonPath('margen_activo', false)
            ->assertJsonPath('margen_porcentaje', 25);
    }

    public function test_margin_is_applied_to_a_whole_category(): void
    {
        $this->admin();
        $producto = Producto::whereNotNull('categoria_id')->firstOrFail();
        $producto->update(['precio_compra' => 10]);

        $this->postJson('/api/productos-margen', ['porcentaje' => 30, 'alcance' => 'categoria', 'categoria_id' => $producto->categoria_id])
            ->assertOk();

        $this->assertEquals(13.00, (float) $producto->fresh()->precio_venta);
        $this->assertSame(0, Producto::where('categoria_id', $producto->categoria_id)->where('precio_compra', '>', 0)
            ->get()->filter(fn ($p) => abs($p->precio_venta - round($p->precio_compra * 1.3, 2)) > 0.001)->count());
    }

    public function test_margin_only_touches_the_selected_products(): void
    {
        $this->admin();
        [$elegido, $otro] = Producto::orderBy('id')->take(2)->get();
        $elegido->update(['precio_compra' => 20]);
        $antes = (float) $otro->precio_venta;

        $this->postJson('/api/productos-margen', ['porcentaje' => 50, 'alcance' => 'seleccion', 'ids' => [$elegido->id]])
            ->assertOk()->assertJsonPath('actualizados', 1);

        $this->assertEquals(30.00, (float) $elegido->fresh()->precio_venta);
        $this->assertEquals($antes, (float) $otro->fresh()->precio_venta);
    }

    public function test_margin_is_rejected_when_disabled(): void
    {
        $this->admin();
        Configuracion::first()->update(['margen_activo' => false]);

        $this->postJson('/api/productos-margen', ['porcentaje' => 30, 'alcance' => 'todos'])->assertStatus(422);
    }

    public function test_category_stores_its_own_margin(): void
    {
        $this->admin();

        $id = $this->postJson('/api/categorias', ['nombre' => 'lacteos', 'color' => 'primary', 'margen_activo' => true, 'margen_porcentaje' => 25])
            ->assertCreated()
            ->assertJsonPath('margen_activo', true)
            ->assertJsonPath('margen_porcentaje', 25)
            ->json('id');

        $this->putJson("/api/categorias/{$id}", ['nombre' => 'LACTEOS', 'margen_activo' => false, 'margen_porcentaje' => 40])
            ->assertOk()
            ->assertJsonPath('margen_activo', false)
            ->assertJsonPath('margen_porcentaje', 40);

        $categoria = collect($this->getJson('/api/productos-catalogos')->assertOk()->json('categorias'))->firstWhere('id', $id);
        $this->assertFalse($categoria['margen_activo']);
        $this->assertEquals(40, $categoria['margen_porcentaje']);
    }
}
