<?php
namespace App\Services;

use App\Models\Product;
use App\Models\SalesOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ContabiliumSyncService {
    public function __construct(private ContabiliumClient $client) {}

    public function syncAll(?callable $shouldContinue=null): ?array {
        $lock=Cache::lock('contabilium.full-sync',300);
        if(!$lock->get()) return null;
        try {
            if($shouldContinue && !$shouldContinue()) return ['products'=>0,'orders'=>0];
            $products=$this->syncProducts($shouldContinue);
            if($shouldContinue && !$shouldContinue()) return ['products'=>$products,'orders'=>0];
            return ['products'=>$products,'orders'=>$this->syncOrders(null,null,$shouldContinue)];
        } finally {
            $lock->release();
        }
    }

    public function syncProducts(?callable $shouldContinue=null): int {
        $count=0; $page=1;
        try {
            do {
                if($shouldContinue && !$shouldContinue()) break;
                $payload=$this->client->products($page); $items=$payload['Items']??[];
                foreach($items as $item) {
                    if($shouldContinue && !$shouldContinue()) break 2;
                    $code=trim((string)($item['Codigo']??''));
                    if($code==='') continue;
                    $contabiliumId=$item['Id']??null;
                    Product::updateOrCreate($contabiliumId!==null?['contabilium_id'=>$contabiliumId]:['code'=>$code],[
                        'code'=>$code,'description'=>$item['Nombre']??$item['Descripcion']??$code,'barcode'=>$item['CodigoBarras']??null,
                        'price'=>$this->decimal($item['PrecioFinal']??$item['Precio']??null),'stock'=>$this->decimal($item['Stock']??null),
                        'is_active'=>strtolower((string)($item['Estado']??'activo'))!=='inactivo','synced_at'=>now(),
                    ]); $count++;
                }
                $totalPages=max(1,(int)($payload['TotalPage']??1)); $page++;
            } while($page<=$totalPages);
            $this->log('products','success',$count); return $count;
        } catch(Throwable $e) { $this->log('products','error',$count,$e->getMessage()); throw $e; }
    }

    public function syncOrders(?Carbon $from=null,?Carbon $to=null,?callable $shouldContinue=null): int {
        $from??=now()->startOfYear(); $to??=now()->endOfYear(); $count=0; $page=1;
        try {
            do {
                if($shouldContinue && !$shouldContinue()) break;
                $payload=$this->client->orders($from->format('Y-m-d'),$to->format('Y-m-d'),$page); $items=$payload['Items']??[];
                foreach($items as $summary) {
                    if($shouldContinue && !$shouldContinue()) break 2;
                    $existing=SalesOrder::where('contabilium_id',$summary['ID'])->first();
                    $status=$existing?->locally_finalized_at ? 'Finalizado' : 'Pendiente';
                    SalesOrder::updateOrCreate(['contabilium_id'=>$summary['ID']],[
                        'customer_id'=>$summary['IDPersona']??null,'number'=>(string)($summary['NumeroOrden']??$summary['ID']),'customer'=>$summary['Comprador']??'Sin comprador',
                        'created_on'=>$this->date($summary['FechaCreacion']??null),'due_on'=>$this->date($summary['FechaVencimiento']??null),'status'=>$status,
                        'currency'=>$summary['Moneda']??null,'total'=>$this->decimal($summary['Total']??null),'integration'=>$summary['Integracion']??null,
                        'warehouse'=>$summary['Deposito']??null,'notes'=>$summary['Observaciones']??null,'raw'=>$summary,'synced_at'=>now(),
                    ]); $count++;
                }
                $perPage=max(1,count($items)); $totalPages=max(1,(int)ceil(((int)($payload['TotalItems']??$perPage))/$perPage)); $page++;
            } while($page<=$totalPages);
            $this->log('orders','success',$count); return $count;
        } catch(Throwable $e) { $this->log('orders','error',$count,$e->getMessage()); throw $e; }
    }

    public function syncOrderDetail(SalesOrder $order,?callable $shouldContinue=null): SalesOrder {
        $detail=$this->client->order($order->contabilium_id);
        if($shouldContinue && !$shouldContinue()) {
            $order->update(['detail_sync_status'=>null]);
            return $order->fresh('items');
        }
        DB::transaction(function () use($order,$detail) {
            $savedAdjustments=[];
            foreach($order->items()->orderBy('id')->get() as $existingItem) {
                if(!$existingItem->label_type || !$existingItem->label_units || !$existingItem->label_count) continue;
                $key=(string)($existingItem->contabilium_concept_id??'').'|'.mb_strtoupper(trim((string)$existingItem->code));
                $savedAdjustments[$key][]=collect($existingItem->toArray())->only([
                    'label_type','label_units','label_count','label_allow_overage','label_adjusted_by','label_adjusted_at',
                ])->all();
            }
            $status=$order->locally_finalized_at ? 'Finalizado' : 'Pendiente';
            $order->update(['customer_id'=>$detail['IDCliente']??$order->customer_id,'customer'=>$detail['Comprador']??$order->customer,
                'created_on'=>$this->date($detail['FechaCreacion']??null)??$order->created_on,'due_on'=>$this->date($detail['FechaVencimiento']??null),
                'number'=>(string)($detail['NumeroOrden']??$order->number),'status'=>$status??$order->status,'total'=>$this->decimal($detail['Total']??null)??$order->total,
                'integration'=>$detail['Integracion']??$order->integration,'notes'=>$detail['Observaciones']??$order->notes,'raw'=>$detail,'details_synced_at'=>now(),
                'detail_sync_status'=>'success','detail_sync_error'=>null,'detail_sync_attempted_at'=>now()]);
            $order->items()->delete();
            foreach($detail['Items']??[] as $item) {
                $product=Product::where('contabilium_id',$item['IdConcepto']??0)->first();
                $code=$item['Codigo']??$product?->code;
                $key=(string)($item['IdConcepto']??'').'|'.mb_strtoupper(trim((string)$code));
                $adjustment=!empty($savedAdjustments[$key]) ? array_shift($savedAdjustments[$key]) : null;
                $order->items()->create(array_merge(['contabilium_concept_id'=>$item['IdConcepto']??null,'code'=>$code,'description'=>$item['Concepto']??$product?->description??'Producto',
                    'quantity'=>$this->decimal($item['Cantidad']??0)??0,'unit_price'=>$this->decimal($item['PrecioUnitario']??null),'tax'=>$this->decimal($item['Iva']??null),'raw'=>$item],$adjustment??[]));
            }
        }); return $order->fresh('items');
    }
    private function decimal(mixed $value): ?float { if($value===null||$value==='') return null; if(is_numeric($value)) return (float)$value; return (float)str_replace(',','.',str_replace('.','',(string)$value)); }
    private function date(?string $value): ?string { if(!$value) return null; foreach(['d/m/Y','Y-m-d'] as $format){try{return Carbon::createFromFormat($format,$value)->format('Y-m-d');}catch(Throwable){}} return null; }
    private function log(string $resource,string $status,int $records,?string $message=null): void { DB::table('contabilium_sync_logs')->insert(['resource'=>$resource,'status'=>$status,'records'=>$records,'message'=>$message,'created_at'=>now(),'updated_at'=>now()]); }
}
