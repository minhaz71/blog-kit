<?php

namespace Tests\Feature;

use App\Services\Network\NetworkIdentity;
use App\Services\Network\NetworkSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class NetworkSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** Sign and send a request to THIS install (spoke), optionally with query. */
    private function signed(string $method, string $path, array $query = [], array $sentQuery = null): TestResponse
    {
        [$key, $secret] = NetworkIdentity::ensure();
        $headers = NetworkSignature::headers($key, $secret, $method, ltrim($path, '/'), '', 'n'.bin2hex(random_bytes(8)), time(), $query);

        $server = ['HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $k => $v) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
        }

        // sentQuery lets a test send DIFFERENT params than were signed (tamper).
        return $this->call($method, $path, $sentQuery ?? $query, [], [], $server);
    }

    public function test_signed_get_with_query_is_accepted(): void
    {
        $this->signed('GET', 'api/v1/network/posts', ['per_page' => 5, 'since' => '2026-01-01T00:00:00Z'])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_query_signing_is_order_independent(): void
    {
        // Signed as a=1,b=2 but sent b first — canonicalQuery ksorts, so it verifies.
        $this->signed('GET', 'api/v1/network/posts', ['per_page' => 3, 'since' => 'x'], ['since' => 'x', 'per_page' => 3])
            ->assertOk();
    }

    public function test_tampered_query_is_rejected(): void
    {
        // Signature covers per_page=5; the wire request asks for 100 → mismatch.
        $this->signed('GET', 'api/v1/network/posts', ['per_page' => 5], ['per_page' => 100])
            ->assertStatus(401)
            ->assertJson(['ok' => false]);
    }

    public function test_added_query_param_is_rejected(): void
    {
        // No query signed, but the wire request smuggles one in.
        $this->signed('GET', 'api/v1/network/posts', [], ['since' => '1999-01-01'])
            ->assertStatus(401);
    }

    public function test_remote_update_is_disabled_by_default(): void
    {
        // No network.allow_remote_update setting → the safer default is OFF.
        $this->signed('POST', 'api/v1/network/update')
            ->assertStatus(403)
            ->assertJson(['ok' => false]);
    }

    public function test_remote_update_default_reflected_in_capabilities(): void
    {
        $res = $this->signed('GET', 'api/v1/network/capabilities')->assertOk();
        // The key literally contains a dot, so index the array (not a json path).
        $this->assertFalse($res->json('capabilities')['remote.update']);
    }
}
