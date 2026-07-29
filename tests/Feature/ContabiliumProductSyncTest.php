<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SalesOrder;
use App\Services\ContabiliumClient;
use App\Services\ContabiliumSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ContabiliumProductSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_sync_updates_api_data_and_preserves_local_packaging(): void
    {
        $product=Product::create([
            'contabilium_id'=>777,
            'code'=>'CODIGO-EDITADO',
            'description'=>'Descripción local',
            'units_fractioned'=>120,
            'units_bulk'=>600,
            'is_active'=>true,
        ]);

        $client=Mockery::mock(ContabiliumClient::class);
        $client->shouldReceive('products')->once()->with(1)->andReturn([
            'Items'=>[[
                'Id'=>777,
                'Codigo'=>'API-777',
                'Nombre'=>'Producto real de Contabilium',
                'Stock'=>35,
                'Estado'=>'Activo',
            ]],
            'TotalPage'=>1,
        ]);

        (new ContabiliumSyncService($client))->syncProducts();

        $product->refresh();
        $this->assertSame('API-777',$product->code);
        $this->assertSame('Producto real de Contabilium',$product->description);
        $this->assertSame(120,$product->units_fractioned);
        $this->assertSame(600,$product->units_bulk);
        $this->assertSame(1,Product::count());
    }

    public function test_pending_api_order_is_imported_as_pending(): void
    {
        $client=Mockery::mock(ContabiliumClient::class);
        $client->shouldReceive('orders')->once()->with('2026-07-01','2026-07-31',1)->andReturn([
            'Items'=>[[
                'ID'=>900,
                'NumeroOrden'=>'00000900',
                'Comprador'=>'Cliente real',
                'FechaCreacion'=>'27/07/2026',
                'Estado'=>'Pendiente',
            ]],
            'TotalItems'=>1,
        ]);

        (new ContabiliumSyncService($client))->syncOrders(
            Carbon::create(2026,7,1),
            Carbon::create(2026,7,31),
        );

        $this->assertDatabaseHas('sales_orders',[
            'contabilium_id'=>900,
            'number'=>'00000900',
            'status'=>'Pendiente',
        ]);
    }

    public function test_api_order_status_is_ignored_and_imported_as_pending(): void
    {
        $client=Mockery::mock(ContabiliumClient::class);
        $client->shouldReceive('orders')->once()->andReturn([
            'Items'=>[[
                'ID'=>902,
                'NumeroOrden'=>'00000902',
                'Comprador'=>'Cliente',
                'FechaCreacion'=>'27/07/2026',
                'Estado'=>'Finalizada',
            ]],
            'TotalItems'=>1,
        ]);

        (new ContabiliumSyncService($client))->syncOrders(
            Carbon::create(2026,7,1),
            Carbon::create(2026,7,31),
        );

        $this->assertDatabaseHas('sales_orders',[
            'contabilium_id'=>902,
            'status'=>'Pendiente',
            'locally_finalized_at'=>null,
        ]);
    }

    public function test_local_finalization_survives_a_pending_api_sync(): void
    {
        SalesOrder::create([
            'contabilium_id'=>901,
            'number'=>'00000901',
            'customer'=>'Cliente',
            'status'=>'Finalizado',
            'locally_finalized_at'=>now(),
        ]);
        $client=Mockery::mock(ContabiliumClient::class);
        $client->shouldReceive('orders')->once()->andReturn([
            'Items'=>[[
                'ID'=>901,
                'NumeroOrden'=>'00000901',
                'Comprador'=>'Cliente',
                'FechaCreacion'=>'27/07/2026',
                'Estado'=>'Pendiente',
            ]],
            'TotalItems'=>1,
        ]);

        (new ContabiliumSyncService($client))->syncOrders(
            Carbon::create(2026,7,1),
            Carbon::create(2026,7,31),
        );

        $this->assertDatabaseHas('sales_orders',['contabilium_id'=>901,'status'=>'Finalizado']);
    }
}
