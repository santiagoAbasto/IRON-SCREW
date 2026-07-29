<?php
namespace App\Console\Commands;
use App\Services\ContabiliumSyncService;
use Illuminate\Console\Command;

class SyncContabilium extends Command {
    protected $signature='contabilium:sync {--products-only} {--orders-only} {--recent}';
    protected $description='Sincroniza productos y órdenes de venta desde Contabilium';
    public function handle(ContabiliumSyncService $sync): int {
        if(!$this->option('products-only') && !$this->option('orders-only')) {
            $result=$sync->syncAll();
            if($result===null) {
                $this->warn('Ya hay una sincronización en curso.');
                return self::SUCCESS;
            }
            $this->info('Productos sincronizados: '.$result['products']);
            $this->info('Órdenes sincronizadas: '.$result['orders']);
            return self::SUCCESS;
        }
        if(!$this->option('orders-only')) $this->info('Productos sincronizados: '.$sync->syncProducts());
        if(!$this->option('products-only')) {
            $from=$this->option('recent')?now()->subDays(7)->startOfDay():null;
            $this->info('Órdenes sincronizadas: '.$sync->syncOrders($from));
        }
        return self::SUCCESS;
    }
}
