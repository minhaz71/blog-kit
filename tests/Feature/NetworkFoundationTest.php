<?php

namespace Tests\Feature;

use App\Services\Network\NetworkIdentity;
use App\Services\Network\NetworkSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkFoundationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    protected function signedHeaders(string $method, string $path, string $body = ''): array
    {
        [$key, $secret] = NetworkIdentity::ensure();

        return NetworkSignature::headers(
            key: $key,
            secret: $secret,
            method: $method,
            path: ltrim($path, '/'),
            body: $body,
            nonce: 'nonce'.bin2hex(random_bytes(8)),
            now: time(),
        );
    }

    public function test_unsigned_network_request_is_rejected(): void
    {
        $this->get('/api/v1/network/ping')->assertStatus(401);
    }

    public function test_correctly_signed_request_is_accepted(): void
    {
        $this->withHeaders($this->signedHeaders('GET', 'api/v1/network/ping'))
            ->get('/api/v1/network/ping')
            ->assertOk()
            ->assertJson(['ok' => true, 'role' => 'hub']);
    }

    public function test_capabilities_handshake_is_declared(): void
    {
        $this->withHeaders($this->signedHeaders('GET', 'api/v1/network/capabilities'))
            ->get('/api/v1/network/capabilities')
            ->assertOk()
            ->assertJsonPath('capabilities.handshake', true)
            ->assertJsonPath('protocol', 'blogkit-network/1');
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $headers = $this->signedHeaders('GET', 'api/v1/network/ping');
        $headers[NetworkSignature::HEADER_SIGNATURE] = str_repeat('0', 64);

        $this->withHeaders($headers)->get('/api/v1/network/ping')->assertStatus(401);
    }

    public function test_replayed_nonce_is_rejected(): void
    {
        $headers = $this->signedHeaders('GET', 'api/v1/network/ping');

        $this->withHeaders($headers)->get('/api/v1/network/ping')->assertOk();
        // Same nonce again → replay.
        $this->withHeaders($headers)->get('/api/v1/network/ping')->assertStatus(401);
    }

    public function test_network_api_is_404_when_module_disabled(): void
    {
        \App\Models\Setting::set('modules.network_enabled', false);
        \Illuminate\Support\Facades\Cache::forget('settings.modules');
        config(['blogkit.modules.network' => false]);

        $this->withHeaders($this->signedHeaders('GET', 'api/v1/network/ping'))
            ->get('/api/v1/network/ping')
            ->assertStatus(404);
    }
}
