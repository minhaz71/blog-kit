<?php

namespace App\Services\Network;

use App\Models\ConnectedSite;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Hub-side client: makes HMAC-signed calls to a spoke's network API using that
 * spoke's shared secret. Every request carries a fresh timestamp + nonce so a
 * captured request can't be replayed.
 */
class NetworkClient
{
    public function __construct(protected int $timeout = 15) {}

    /** Health handshake. Returns the decoded response array. */
    public function ping(ConnectedSite $site): array
    {
        return $this->request($site, 'GET', 'ping');
    }

    /** Feature/capability handshake. */
    public function capabilities(ConnectedSite $site): array
    {
        return $this->request($site, 'GET', 'capabilities');
    }

    /**
     * Sign and send. $path is the network sub-path ("ping"); the full signed
     * path is "api/v1/network/<path>" so it matches what the spoke verifies.
     *
     * @throws \RuntimeException on transport failure or non-2xx response
     */
    public function request(ConnectedSite $site, string $method, string $path, array $body = [], array $query = []): array
    {
        $fullPath = 'api/v1/network/'.ltrim($path, '/');
        $payload = $body === [] ? '' : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // The signature covers the path + body (not the query string); the
        // spoke verifies against $request->path(), which excludes the query.
        $headers = NetworkSignature::headers(
            key: (string) $site->api_key,
            secret: (string) $site->api_secret,
            method: $method,
            path: $fullPath,
            body: $payload,
            nonce: Str::random(32),
            now: time(),
        );

        try {
            $request = Http::withHeaders($headers)
                ->timeout($this->timeout)
                ->acceptJson();

            $response = ($method === 'GET')
                ? $request->get($site->apiUrl($path), $query)
                : $request->withBody($payload, 'application/json')->send($method, $site->apiUrl($path));
        } catch (\Throwable $e) {
            throw new \RuntimeException('Could not reach '.$site->baseUrl().': '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            $error = (string) ($response->json('error') ?? $response->reason() ?? 'HTTP '.$response->status());

            throw new \RuntimeException("Site returned {$response->status()}: {$error}");
        }

        return (array) $response->json();
    }

    /**
     * Ping a site and persist the health result (status, version,
     * capabilities, last_seen_at, last_error). Returns [ok, message].
     *
     * @return array{0: bool, 1: string}
     */
    public function refreshHealth(ConnectedSite $site): array
    {
        try {
            $caps = $this->capabilities($site);

            $site->forceFill([
                'status' => 'online',
                'remote_version' => $caps['version'] ?? null,
                'capabilities' => $caps['capabilities'] ?? null,
                'last_seen_at' => now(),
                'last_error' => null,
            ])->save();

            return [true, 'Connected to '.($caps['name'] ?? $site->name).' (v'.($caps['version'] ?? '?').').'];
        } catch (\Throwable $e) {
            $site->forceFill([
                'status' => 'error',
                'last_error' => Str::limit($e->getMessage(), 480),
            ])->save();

            return [false, $e->getMessage()];
        }
    }
}
