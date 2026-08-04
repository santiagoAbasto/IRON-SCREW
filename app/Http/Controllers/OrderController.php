<?php

namespace App\Http\Controllers;

use App\Jobs\SyncContabiliumJob;
use App\Jobs\SyncContabiliumOrderDetailJob;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Services\QueueWorkerLauncher;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $q = (string) $request->string('q');
        $orders = SalesOrder::query()
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) <> ?", ['cancelado'])
            ->when($q, fn ($query) => $query->where(fn ($s) => $s
                ->where('number', 'like', "%{$q}%")
                ->orWhere('customer', 'like', "%{$q}%")))
            ->orderByRaw("CASE LOWER(TRIM(status))
                WHEN 'pendiente' THEN 1
                WHEN 'finalizado' THEN 2
                WHEN 'cancelado' THEN 3
                ELSE 4
            END")
            ->latest('created_on')
            ->paginate(20)
            ->withQueryString();
        $lastSync = SalesOrder::whereNotNull('synced_at')->latest('synced_at')->first()?->synced_at;

        return view('orders.index', compact('orders', 'q', 'lastSync'));
    }

    public function show(Request $request, SalesOrder $order, QueueWorkerLauncher $worker)
    {
        $this->ensureOrderIsVisible($order);
        $order->load('items.adjustedBy');
        $stale = ! $order->details_synced_at || $order->details_synced_at->lt(now()->subMinutes(10));
        $detailRefreshing = $stale && in_array($order->detail_sync_status, ['queued', 'running'], true);
        $retryDue = $order->detail_sync_status === 'error'
            && (! $order->detail_sync_attempted_at || $order->detail_sync_attempted_at->lte(now()->subMinutes(15)));
        if ($stale && ! $detailRefreshing && ($order->detail_sync_status !== 'error' || $retryDue)) {
            $order->update(['detail_sync_status' => 'queued', 'detail_sync_error' => null, 'detail_sync_attempted_at' => now()]);
            SyncContabiliumOrderDetailJob::dispatch($order->id, $request->session()->getId());
            $worker->start();
            $detailRefreshing = true;
        }
        $products = Product::whereIn('code', $order->items->pluck('code')->filter())->get()->keyBy('code');

        return view('orders.show', compact('order', 'products', 'detailRefreshing'));
    }

    public function refreshDetail(Request $request, SalesOrder $order, QueueWorkerLauncher $worker)
    {
        $this->ensureOrderIsVisible($order);
        $order->update(['detail_sync_status' => 'queued', 'detail_sync_error' => null, 'detail_sync_attempted_at' => now()]);
        SyncContabiliumOrderDetailJob::dispatch($order->id, $request->session()->getId());
        $worker->start();

        return back()->with('success', 'Actualización de artículos agregada a la cola. Al finalizar, podrás reimprimir las etiquetas con las cantidades nuevas.');
    }

    public function sync(Request $request, QueueWorkerLauncher $worker)
    {
        SyncContabiliumJob::dispatch($request->session()->getId());
        $worker->start();

        return back()->with('success', 'Sincronización agregada a la cola. Podés seguir usando el sistema o cerrar sesión.');
    }

    public function saveLabelAdjustment(Request $request, SalesOrder $order, string $item)
    {
        $this->ensureOrderIsVisible($order);
        $data = $request->validate([
            'type' => ['required', 'in:bulk,fractioned,order'],
            'units' => ['required', 'numeric', 'min:1'],
            'count' => ['required', 'integer', 'min:1'],
            'allow_overage' => ['nullable', 'boolean'],
            'concept_id' => ['nullable', 'integer'],
            'code' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'line_index' => ['nullable', 'integer', 'min:0'],
        ]);
        $orderItem = $order->items()->find($item);
        if (!$orderItem && !empty($data['concept_id'])) {
            $orderItem = $order->items()
                ->where('contabilium_concept_id', $data['concept_id'])
                ->when(!empty($data['code']), fn ($query) => $query->where('code', $data['code']))
                ->when(!empty($data['description']), fn ($query) => $query->where('description', $data['description']))
                ->first();
        }
        if (!$orderItem && !empty($data['code'])) {
            $orderItem = $order->items()
                ->where('code', $data['code'])
                ->when(!empty($data['description']), fn ($query) => $query->where('description', $data['description']))
                ->first();
        }
        if (!$orderItem && isset($data['line_index'])) {
            $orderItem = $order->items()->orderBy('id')->skip($data['line_index'])->first();
        }
        abort_unless($orderItem, 404, 'El artículo ya no existe en esta orden. Actualizá la página.');
        $user = $request->attributes->get('ironUser');
        $orderItem->update([
            'label_type' => $data['type'],
            'label_units' => $data['units'],
            'label_count' => $data['count'],
            'label_allow_overage' => $request->boolean('allow_overage'),
            'label_adjusted_by' => $user->id,
            'label_adjusted_at' => now(),
        ]);

        return response()->json([
            'adjustment' => [
                'type' => $orderItem->label_type,
                'units' => (float) $orderItem->label_units,
                'count' => $orderItem->label_count,
                'allowOverage' => $orderItem->label_allow_overage,
                'adjustedBy' => $user->name,
            ],
        ]);
    }

    public function finalize(SalesOrder $order)
    {
        $this->ensureOrderIsVisible($order);
        abort_unless(mb_strtolower(trim((string) $order->status)) === 'pendiente', 422, 'Solo las órdenes pendientes pueden finalizarse desde este sistema.');
        $order->update(['status' => 'Finalizado', 'locally_finalized_at' => now()]);

        return back()->with('success', "La orden {$order->number} fue finalizada.");
    }

    private function ensureOrderIsVisible(SalesOrder $order): void
    {
        abort_if(mb_strtolower(trim((string) $order->status)) === 'cancelado',404);
    }
}
