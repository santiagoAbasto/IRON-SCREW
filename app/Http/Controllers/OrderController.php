<?php
namespace App\Http\Controllers;
use App\Jobs\SyncContabiliumJob;
use App\Jobs\SyncContabiliumOrderDetailJob;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Services\QueueWorkerLauncher;
use Illuminate\Http\Request;

class OrderController extends Controller {
    public function index(Request $request) {
        $q=(string)$request->string('q');
        $orders=SalesOrder::query()
            ->when($q,fn($query)=>$query->where(fn($s)=>$s
                ->where('number','like',"%{$q}%")
                ->orWhere('customer','like',"%{$q}%")))
            ->orderByRaw("CASE LOWER(TRIM(status))
                WHEN 'pendiente' THEN 1
                WHEN 'finalizado' THEN 2
                WHEN 'cancelado' THEN 3
                ELSE 4
            END")
            ->latest('created_on')
            ->paginate(20)
            ->withQueryString();
        $lastSync=SalesOrder::whereNotNull('synced_at')->latest('synced_at')->first()?->synced_at;
        return view('orders.index',compact('orders','q','lastSync'));
    }
    public function show(Request $request, SalesOrder $order,QueueWorkerLauncher $worker) {
        $order->load('items');
        $stale=!$order->details_synced_at || $order->details_synced_at->lt(now()->subMinutes(10));
        $detailRefreshing=$stale && in_array($order->detail_sync_status,['queued','running'],true);
        $retryDue=$order->detail_sync_status==='error'
            && (!$order->detail_sync_attempted_at || $order->detail_sync_attempted_at->lte(now()->subMinutes(15)));
        if($stale && !$detailRefreshing && ($order->detail_sync_status!=='error' || $retryDue)) {
            $order->update(['detail_sync_status'=>'queued','detail_sync_error'=>null,'detail_sync_attempted_at'=>now()]);
            SyncContabiliumOrderDetailJob::dispatch($order->id,$request->session()->getId());
            $worker->start();
            $detailRefreshing=true;
        }
        $products=Product::whereIn('code',$order->items->pluck('code')->filter())->get()->keyBy('code');
        return view('orders.show',compact('order','products','detailRefreshing'));
    }
    public function refreshDetail(Request $request, SalesOrder $order,QueueWorkerLauncher $worker) {
        $order->update(['detail_sync_status'=>'queued','detail_sync_error'=>null,'detail_sync_attempted_at'=>now()]);
        SyncContabiliumOrderDetailJob::dispatch($order->id,$request->session()->getId());
        $worker->start();
        return back()->with('success','Actualización del detalle agregada a la cola.');
    }
    public function sync(Request $request,QueueWorkerLauncher $worker) {
        SyncContabiliumJob::dispatch($request->session()->getId());
        $worker->start();
        return back()->with('success','Sincronización agregada a la cola. Podés seguir usando el sistema o cerrar sesión.');
    }
    public function finalize(SalesOrder $order) {
        abort_unless(mb_strtolower(trim((string)$order->status))==='pendiente',422,'Solo las órdenes pendientes pueden finalizarse desde este sistema.');
        $order->update(['status'=>'Finalizado','locally_finalized_at'=>now()]);
        return back()->with('success',"La orden {$order->number} fue finalizada.");
    }
}
