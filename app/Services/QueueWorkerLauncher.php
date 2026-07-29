<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

class QueueWorkerLauncher
{
    public function start(): void
    {
        $lock = Cache::lock('queue.worker.launching', 5);

        if (! $lock->get()) {
            return;
        }

        try {
            $log = storage_path('logs/queue-worker.log');
            $command = sprintf(
                'nohup %s %s queue:work --stop-when-empty --sleep=1 --tries=20 --timeout=240 < /dev/null >> %s 2>&1 &',
                escapeshellarg(PHP_BINARY),
                escapeshellarg(base_path('artisan')),
                escapeshellarg($log),
            );

            Process::path(base_path())
                ->timeout(5)
                ->run($command)
                ->throw();
        } finally {
            $lock->release();
        }
    }
}
