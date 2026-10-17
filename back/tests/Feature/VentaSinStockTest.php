<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Se puede vender sin stock, pero el stock nunca queda negativo. */
class VentaSinStockTest extends TestCase
{
    use RefreshDatabase;

    private function vender(Producto $producto, float $cantidad, ?array $otraLinea = null)
    {
        Sanctum::actingAs(User::where('username', 'admin')->firstOrFail());
        $detalles = [['producto_id' => $producto->id, 'cantidad' => $cantidad, 'precio_venta' => 10]];
        if ($otraLinea) {
            $detalles[] = $otraLinea;
        }

        return $this->postJson('/api/ventas', ['tipo_pago' => 'EFECTIVO', 'detalles' => $detalles]);
    }

    private function producto(float $stock): Producto
    {
        $producto = Producto::firstOrFail();
        $producto->update(['stock_inicial' => $stock, 'precio_venta' => 10, 'unidad' => 'UNIDAD']);

        return $producto;
    }

    public function test_se_vende_un_producto_en_cero_y_el_stock_sigue_en_cero(): void
    {
        $producto = $this->producto(0);

        $this->vender($producto, 3)->assertCreated()->assertJsonPath('total', '30.00');

        $this->assertEqualsWithDelta(0, (float) $producto->fresh()->stock_inicial, 0.001);
        $this->assertDatabaseHas('venta_detalles', ['producto_id' => $producto->id, 'cantidad' => 3, 'cantidad_descontada' => 0]);
    }

    public function test_si_no_alcanza_descuenta_lo_que_hay_y_consume_solo_esos_lotes(): void
    {
        $producto = $this->producto(2);
        $lote = Lote::create(['producto_id' => $producto->id, 'lote' => 'L1', 'cantidad_inicial' => 2, 'cantidad_disponible' => 2]);

        $this->vender($producto, 5)->assertCreated();

        $this->assertEqualsWithDelta(0, (float) $producto->fresh()->stock_inicial, 0.001);
        $this->assertEqualsWithDelta(0, (float) $lote->fresh()->cantidad_disponible, 0.001);
        $this->assertDatabaseHas('venta_detalles', ['producto_id' => $producto->id, 'cantidad' => 5, 'cantidad_descontada' => 2]);
    }

    public function test_dos_lineas_del_mismo_producto_no_dejan_negativo(): void
    {
        $producto = $this->producto(4);

        $this->vender($producto, 3, ['producto_id' => $producto->id, 'cantidad' => 3, 'precio_venta' => 10])->assertCreated();

        $this->assertEqualsWithDelta(0, (float) $producto->fresh()->stock_inicial, 0.001);
    }

    public function test_anular_devuelve_solo_lo_que_se_desconto(): void
    {
        $producto = $this->producto(2);

        $venta = $this->vender($producto, 5)->assertCreated();
        $this->putJson("/api/ventas/{$venta->json('id')}/anular")->assertOk();

        $this->assertEqualsWithDelta(2, (float) $producto->fresh()->stock_inicial, 0.001);
    }
}
