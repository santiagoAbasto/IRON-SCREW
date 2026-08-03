<?php

namespace Tests\Feature;

use App\Jobs\SyncContabiliumOrderDetailJob;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_new_orders_can_be_finalized_locally(): void
    {
        $role = Role::create(['name' => 'Administrador', 'permissions' => ['orders.view', 'orders.manage']]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $session = ['iron_user' => $user->id];
        $new = SalesOrder::create(['contabilium_id' => 1, 'number' => 'OV-1', 'customer' => 'Cliente', 'status' => 'Pendiente']);
        $apiFinalized = SalesOrder::create(['contabilium_id' => 2, 'number' => 'OV-2', 'customer' => 'Cliente', 'status' => 'Finalizado']);

        $this->withSession($session)->patch(route('orders.finalize', $new))->assertRedirect();
        $new->refresh();
        $this->assertSame('Finalizado', $new->status);
        $this->assertNotNull($new->locally_finalized_at);

        $this->withSession($session)->patch(route('orders.finalize', $apiFinalized))->assertStatus(422);
    }

    public function test_cancelled_orders_are_hidden_from_the_list_and_detail(): void
    {
        $role = Role::create(['name' => 'Consulta', 'permissions' => ['orders.view']]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $visible = SalesOrder::create(['contabilium_id' => 30, 'number' => 'OV-VISIBLE', 'customer' => 'Cliente', 'status' => 'Pendiente']);
        $cancelled = SalesOrder::create(['contabilium_id' => 31, 'number' => 'OV-CANCELADA', 'customer' => 'Cliente', 'status' => 'Cancelado']);
        $session = ['iron_user' => $user->id];

        $this->withSession($session)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee($visible->number)
            ->assertDontSee($cancelled->number);

        $this->withSession($session)
            ->get(route('orders.show', $cancelled))
            ->assertNotFound();
    }

    public function test_order_detail_offers_batch_label_printing(): void
    {
        $role = Role::create(['name' => 'Consulta', 'permissions' => ['orders.view']]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $order = SalesOrder::create([
            'contabilium_id' => 32,
            'number' => 'OV-ETIQUETAS',
            'customer' => 'Cliente',
            'status' => 'Pendiente',
            'details_synced_at' => now(),
        ]);
        $order->items()->create(['code' => 'ET-1', 'description' => 'Producto etiquetable', 'quantity' => 4]);

        $this->withSession(['iron_user' => $user->id])
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Imprimir todas las etiquetas')
            ->assertSee('data-print-all-labels', false)
            ->assertSee('data-save-label-adjustment', false)
            ->assertSee('Guardar ajuste');
    }

    public function test_order_detail_marks_only_the_requested_quantity_when_no_packaging_matches(): void
    {
        $role = Role::create(['name' => 'Consulta', 'permissions' => ['orders.view']]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $order = SalesOrder::create([
            'contabilium_id' => 10,
            'number' => 'OV-PARCIAL',
            'customer' => 'Cliente',
            'status' => 'Pendiente',
            'details_synced_at' => now(),
        ]);
        Product::create([
            'contabilium_id' => 20,
            'code' => 'CAJA-2',
            'description' => 'Producto',
            'units_bulk' => 2,
            'is_active' => true,
        ]);
        $order->items()->create(['code' => 'CAJA-2', 'description' => 'Producto', 'quantity' => 3]);

        $this->withSession(['iron_user' => $user->id])
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('quantity-mismatch', false)
            ->assertDontSee('bulk-mismatch', false)
            ->assertSee('>0</a>', false)
            ->assertSee('2')
            ->assertDontSee('⚠');
    }

    public function test_fractioned_packaging_also_prevents_the_quantity_warning(): void
    {
        $role = Role::create(['name' => 'Consulta', 'permissions' => ['orders.view']]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $order = SalesOrder::create([
            'contabilium_id' => 12,
            'number' => 'OV-FRACCIONADO',
            'customer' => 'Cliente',
            'status' => 'Pendiente',
            'details_synced_at' => now(),
        ]);
        Product::create([
            'contabilium_id' => 21,
            'code' => 'FRACCION-500',
            'description' => 'Producto fraccionado',
            'units_fractioned' => 500,
            'units_bulk' => 3000,
            'is_active' => true,
        ]);
        $order->items()->create(['code' => 'FRACCION-500', 'description' => 'Producto fraccionado', 'quantity' => 1000]);

        $this->withSession(['iron_user' => $user->id])
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertDontSee('quantity-mismatch', false)
            ->assertSee('quantity-review', false)
            ->assertSee('Revisar presentación')
            ->assertSee('data-item-box-total', false)
            ->assertSee('<strong>2</strong>', false)
            ->assertSee('cajas fraccionadas');
    }

    public function test_one_exact_fractioned_box_does_not_request_packaging_review(): void
    {
        $role = Role::create(['name' => 'Consulta', 'permissions' => ['orders.view']]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $order = SalesOrder::create([
            'contabilium_id' => 1136,
            'number' => 'OV-1136',
            'customer' => 'Cliente',
            'status' => 'Pendiente',
            'details_synced_at' => now(),
        ]);
        Product::create([
            'contabilium_id' => 1136,
            'code' => 'DISCO-25',
            'description' => 'Disco de corte',
            'units_fractioned' => 25,
            'units_bulk' => 400,
            'is_active' => true,
        ]);
        $order->items()->create(['code' => 'DISCO-25', 'description' => 'Disco de corte', 'quantity' => 25]);

        $this->withSession(['iron_user' => $user->id])
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertDontSee('quantity-review', false)
            ->assertDontSee('Revisar presentación')
            ->assertSee('<strong>1</strong>', false)
            ->assertSee('caja fraccionada');
    }

    public function test_zero_bulk_uses_the_exact_customer_order_quantity(): void
    {
        $role = Role::create(['name' => 'Consulta', 'permissions' => ['orders.view']]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $order = SalesOrder::create([
            'contabilium_id' => 1200,
            'number' => 'OV-A-PEDIDO',
            'customer' => 'Cliente',
            'status' => 'Pendiente',
            'details_synced_at' => now(),
        ]);
        Product::create([
            'contabilium_id' => 1200,
            'code' => 'A-PEDIDO',
            'description' => 'Producto por cantidad pedida',
            'units_fractioned' => 500,
            'units_bulk' => 0,
            'label_exact_order' => true,
            'is_active' => true,
        ]);
        $order->items()->create(['code' => 'A-PEDIDO', 'description' => 'Producto por cantidad pedida', 'quantity' => 1375]);

        $this->withSession(['iron_user' => $user->id])
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertDontSee('quantity-mismatch', false)
            ->assertDontSee('quantity-review', false)
            ->assertSee('A pedido')
            ->assertSee('data-exact-order="true"', false)
            ->assertSee('<option value="order">Cantidad pedida</option>', false)
            ->assertSee('<strong>1</strong>', false)
            ->assertSee('caja a pedido');
    }

    public function test_both_zero_packages_automatically_use_exact_order_quantity(): void
    {
        $role = Role::create(['name' => 'Consulta', 'permissions' => ['orders.view']]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $order = SalesOrder::create(['contabilium_id' => 1201, 'number' => 'OV-SIN-BULTOS', 'customer' => 'Cliente', 'status' => 'Pendiente', 'details_synced_at' => now()]);
        Product::create(['contabilium_id' => 1201, 'code' => 'SIN-BULTOS', 'description' => 'Producto sin bultos', 'units_fractioned' => 0, 'units_bulk' => 0, 'label_exact_order' => false, 'is_active' => true]);
        $order->items()->create(['code' => 'SIN-BULTOS', 'description' => 'Producto sin bultos', 'quantity' => 20]);

        $this->withSession(['iron_user' => $user->id])
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertDontSee('quantity-mismatch', false)
            ->assertSee('data-exact-order="true"', false)
            ->assertSee('caja a pedido')
            ->assertSee('<option value="order">Cantidad pedida</option>', false);
    }

    public function test_only_one_zero_package_remains_partially_undefined(): void
    {
        $role = Role::create(['name' => 'Consulta', 'permissions' => ['orders.view']]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $order = SalesOrder::create(['contabilium_id' => 1202, 'number' => 'OV-PARCIAL', 'customer' => 'Cliente', 'status' => 'Pendiente', 'details_synced_at' => now()]);
        Product::create(['contabilium_id' => 1202, 'code' => 'PARCIAL', 'description' => 'Producto parcial', 'units_fractioned' => 10, 'units_bulk' => 0, 'label_exact_order' => false, 'is_active' => true]);
        $order->items()->create(['code' => 'PARCIAL', 'description' => 'Producto parcial', 'quantity' => 25]);

        $this->withSession(['iron_user' => $user->id])
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('quantity-mismatch', false)
            ->assertSee('data-exact-order="false"', false)
            ->assertDontSee('caja a pedido');
    }

    public function test_stale_order_opens_from_local_data_and_refreshes_in_queue(): void
    {
        Queue::fake();
        Process::fake();
        Http::preventStrayRequests();
        $role = Role::create(['name' => 'Consulta', 'permissions' => ['orders.view']]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $order = SalesOrder::create([
            'contabilium_id' => 11,
            'number' => 'OV-CACHE',
            'customer' => 'Cliente local',
            'status' => 'Pendiente',
            'details_synced_at' => now()->subHour(),
        ]);
        $order->items()->create(['code' => 'LOCAL-1', 'description' => 'Artículo guardado', 'quantity' => 4]);

        $this->withSession(['iron_user' => $user->id])
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Artículo guardado')
            ->assertSee('Actualizando el detalle en segundo plano');

        Queue::assertPushed(
            SyncContabiliumOrderDetailJob::class,
            fn ($job) => $job->orderId === $order->id
        );
        Process::assertRan(fn ($process) => str_contains((string) $process->command, 'queue:work --stop-when-empty'));
    }
}
