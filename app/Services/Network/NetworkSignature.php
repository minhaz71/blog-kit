<?php

namespace App\Services\Network;

/**
 * Symmetric HMAC-SHA256 request signing for the multisite network API — the
 * same key/secret + hash_equals discipline used by the payment webhooks.
 *
 * Canonical string (newline-joined, order fixed):
 *   METHOD \n /path \n timestamp \n nonce \n sha256hex(body)
 *
 * The hub signs outbound requests with a spoke's shared secret; the spoke
 * verifies with the same secret. Timestamp + nonce defeat replay.
 */
class NetworkSignature
{
    public const HEADER_KEY = 'X-BlogKit-Key';
    public const HEADER_TIMESTAMP = 'X-BlogKit-Timestamp';
    public const HEADER_NONCE = 'X-BlogKit-Nonce';
    public const HEADER_SIGNATURE = 'X-BlogKit-Signature';

    /** Build the canonical string that gets HMAC'd. Path is normalized to a leading slash. */
    public static function canonical(string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        return implode("\n", [
            strtoupper($method),
            '/'.ltrim($path, '/'),
            $timestamp,
            $nonce,
            hash('sha256', $body),
        ]);
    }

    public static function sign(string $secret, string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        return hash_hmac('sha256', self::canonical($method, $path, $timestamp, $nonce, $body), $secret);
    }

    /**
     * Signed request headers for an outbound call. Timestamp + nonce are
     * generated here. $randomHex must be a caller-supplied 32-hex-char nonce
     * and $now a unix timestamp (passed in so this stays pure/testable — the
     * workflow runtime forbids Date/random in some contexts; callers use
     * Str::random / time()).
     *
     * @return array<string, string>
     */
    public static function headers(string $key, string $secret, string $method, string $path, string $body, string $nonce, int $now): array
    {
        $ts = (string) $now;

        return [
            self::HEADER_KEY => $key,
            self::HEADER_TIMESTAMP => $ts,
            self::HEADER_NONCE => $nonce,
            self::HEADER_SIGNATURE => self::sign($secret, $method, $path, $ts, $nonce, $body),
        ];
    }

    /** Constant-time verification of a provided signature. */
    public static function verify(string $secret, string $method, string $path, string $timestamp, string $nonce, string $body, string $provided): bool
    {
        $expected = self::sign($secret, $method, $path, $timestamp, $nonce, $body);

        return hash_equals($expected, $provided);
    }
}
