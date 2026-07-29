<?php
namespace App\Jobs;

use App\Services\ContabiliumSyncService;
use App\Services\SessionProcessCancellation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncContabiliumJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 240;
    public int $tries = 20;
    public bool $failOnTimeout = true;

    public function __construct(public ?string $sessionId = null)
    {
    }

    public function handle(ContabiliumSyncService $sync, SessionProcessCancellation $cancellation): void
    {
        $lastCheck = 0.0;
        $allowed = true;
        $continue = function () use ($cancellation, &$lastCheck, &$allowed): bool {
            if ($this->sessionId === null) {
                return true;
            }
            if ((microtime(true) - $lastCheck) < 1) {
                return $allowed;
            }
            $lastCheck = microtime(true);
            return $allowed = !$cancellation->isCancelled($this->sessionId);
        };
        if (!$continue()) {
            return;
        }

        try {
            $result = $sync->syncAll($continue);
            if ($result === null && $continue()) {
                $this->release(10);
            }
        } catch (Throwable $exception) {
            Log::warning('No se pudo completar la sincronización con Contabilium.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
