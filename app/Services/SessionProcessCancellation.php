<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SessionProcessCancellation
{
    public function cancel(string $sessionId): void
    {
        if ($sessionId !== '') {
            Cache::put($this->key($sessionId), true, now()->addDay());
        }
    }

    public function clear(string $sessionId): void
    {
        if ($sessionId !== '') {
            Cache::forget($this->key($sessionId));
        }
    }

    public function isCancelled(string $sessionId): bool
    {
        if ($sessionId === '' || Cache::get($this->key($sessionId), false)) {
            return true;
        }

        $lastActivity = DB::table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->value('last_activity');

        if ($lastActivity === null) {
            return true;
        }

        $expiresAt = now()->subMinutes((int) config('session.lifetime', 120))->timestamp;

        return (int) $lastActivity < $expiresAt;
    }

    private function key(string $sessionId): string
    {
        return 'contabilium.session.cancelled.'.hash('sha256', $sessionId);
    }
}
