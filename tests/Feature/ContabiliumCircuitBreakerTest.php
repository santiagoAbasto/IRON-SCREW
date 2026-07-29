<?php

namespace Tests\Feature;

use App\Services\ContabiliumClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ContabiliumCircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    public function test_cloudflare_block_pauses_new_api_attempts(): void
    {
        config([
            'contabilium.base_url' => 'https://rest.contabilium.test',
            'contabilium.email' => 'api@example.com',
            'contabilium.api_key' => 'secret',
        ]);
        Cache::forget('contabilium.access_token');
        Cache::forget('contabilium.blocked_until');
        Http::fake([
            'rest.contabilium.test/token' => Http::response(
                '<html><title>Attention Required! | Cloudflare</title></html>',
                403,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $client=app(ContabiliumClient::class);
        foreach([1,2] as $_) {
            try {
                $client->token();
            } catch(RuntimeException $exception) {
                $this->assertStringContainsString('no está disponible', $exception->getMessage());
            }
        }

        Http::assertSentCount(1);
        $this->assertNotNull(Cache::get('contabilium.blocked_until'));
    }
}
