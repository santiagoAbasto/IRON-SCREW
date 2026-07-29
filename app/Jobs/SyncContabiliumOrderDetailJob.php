<?php

namespace App\Jobs;

use App\Models\SalesOrder;
use App\Services\ContabiliumSyncService;
use App\Services\SessionProcessCancellation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncContabiliumOrderDetailJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 45;
    public int $tries = 1;
    public bool $failOnTimeout = true;

    public function __construct(public int $orderId, public ?string $sessionId = null)
    {
    }

    public function handle(ContabiliumSyncService $sync, SessionProcessCancellation $cancellation): void
    {
        $order = SalesOrder::find($this->orderId);
        if (!$order) {
            return;
        }

        $continue = fn (): bool => $this->sessionId === null || !$cancellation->isCancelled($this->sessionId);
        if (!$continue()) {
            if ($order->detail_sync_status === 'queued') {
                $order->update(['detail_sync_status' => null]);
            }
            return;
        }

        $order->update([
            'detail_sync_status' => 'running',
            'detail_sync_error' => null,
            'detail_sync_attempted_at' => now(),
        ]);

        try {
            $sync->syncOrderDetail($order, $continue);
        } catch (Throwable $exception) {
            $message = str_contains($exception->getMessage(),'no está disponible')
                ? $exception->getMessage()
                : 'No se pudo conectar con Contabilium. Volveremos a intentarlo automáticamente.';
            $order->update([
                'detail_sync_status' => 'error',
                'detail_sync_error' => mb_substr($message, 0, 1000),
                'detail_sync_attempted_at' => now(),
            ]);
            Log::warning('No se pudo actualizar el detalle de la orden desde Contabilium.', [
                'order_id' => $order->id,
                'contabilium_id' => $order->contabilium_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
