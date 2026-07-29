<?php
namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ContabiliumClient {
    private const BLOCKED_UNTIL_CACHE_KEY = 'contabilium.blocked_until';

    public function request(): PendingRequest {
        $this->ensureAvailable();
        return Http::baseUrl(rtrim(config('contabilium.base_url'),'/'))
            ->withToken($this->token())
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->retry(2,750,fn($exception)=>$this->shouldRetry($exception));
    }
    public function token(): string {
        $this->ensureAvailable();
        return Cache::remember('contabilium.access_token', now()->addHours(20), function () {
            $email=config('contabilium.email'); $key=config('contabilium.api_key');
            if(!$email||!$key) throw new RuntimeException('Faltan CONTABILIUM_EMAIL o CONTABILIUM_API_KEY.');
            $response=Http::asForm()->connectTimeout(5)->timeout(15)
                ->retry(2,750,fn($exception)=>$this->shouldRetry($exception),false)
                ->post(rtrim(config('contabilium.base_url'),'/').'/token',['grant_type'=>'client_credentials','client_id'=>$email,'client_secret'=>$key]);
            if($response->status()===403 && str_contains(mb_strtolower($response->body()),'cloudflare')) {
                Cache::put(self::BLOCKED_UNTIL_CACHE_KEY,now()->addMinutes(15)->toIso8601String(),now()->addMinutes(15));
                throw new RuntimeException('El servicio de Contabilium no está disponible temporalmente. El próximo intento automático se realizará en 15 minutos.');
            }
            $response->throw();
            Cache::forget(self::BLOCKED_UNTIL_CACHE_KEY);
            return (string)$response->json('access_token');
        });
    }
    public function products(int $page=1,int $pageSize=200): array {
        return $this->request()->get('/api/conceptos/search',['page'=>$page,'pageSize'=>$pageSize])->throw()->json();
    }
    public function orders(string $from,string $to,int $page=1): array {
        return $this->request()->get('/api/ordenesVenta/search',['fechaDesde'=>$from,'fechaHasta'=>$to,'page'=>$page])->throw()->json();
    }
    public function order(int $id): array {
        return $this->request()->get('/api/ordenesVenta/',['id'=>$id])->throw()->json();
    }
    private function shouldRetry(mixed $exception): bool {
        if($exception instanceof ConnectionException) return true;
        if(!$exception instanceof RequestException) return false;
        $status=$exception->response->status();
        return $status===429 || $status>=500;
    }
    private function ensureAvailable(): void {
        $blockedUntil=Cache::get(self::BLOCKED_UNTIL_CACHE_KEY);
        if(!$blockedUntil) return;
        $until=\Carbon\Carbon::parse($blockedUntil);
        if($until->isPast()) {
            Cache::forget(self::BLOCKED_UNTIL_CACHE_KEY);
            return;
        }
        throw new RuntimeException('El servicio de Contabilium no está disponible temporalmente. Próximo intento automático '.$until->format('H:i').'.');
    }
}
