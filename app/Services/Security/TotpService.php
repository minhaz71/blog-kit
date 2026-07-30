<?php

namespace App\Services\Security;

/**
 * RFC 6238 TOTP (Time-based One-Time Password) — pure PHP, no external deps.
 * Compatible with Google Authenticator, Authy, 1Password, etc.
 */
class TotpService
{
    protected int $digits = 6;
    protected int $period = 30;
    protected string $algorithm = 'sha1';

    /** Generate a fresh 32-char base32 secret suitable for provisioning URIs. */
    public function generateSecret(int $length = 20): string
    {
        return $this->base32Encode(random_bytes($length));
    }

    /** otpauth:// URI for QR-code provisioning. */
    public function provisioningUri(string $secret, string $accountName, string $issuer): string
    {
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper($this->algorithm),
            'digits' => $this->digits,
            'period' => $this->period,
        ]);

        return sprintf(
            'otpauth://totp/%s:%s?%s',
            rawurlencode($issuer),
            rawurlencode($accountName),
            $params,
        );
    }

    /** Verify a 6-digit code, allowing ±1 step drift. */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timestamp = (int) floor(time() / $this->period);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->generateCode($secret, $timestamp + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    /** Compute the code for a specific time-step. */
    public function generateCode(string $secret, int $timestamp): string
    {
        $key = $this->base32Decode($secret);
        $binaryTime = pack('N*', 0).pack('N*', $timestamp);
        $hash = hash_hmac($this->algorithm, $binaryTime, $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0xF;
        $truncated =
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($truncated % (10 ** $this->digits)), $this->digits, '0', STR_PAD_LEFT);
    }

    // ── base32 (RFC 4648) — used by otpauth URIs ──────────────────
    protected string $b32Alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    protected function base32Encode(string $bytes): string
    {
        $out = '';
        $buffer = 0;
        $bitsLeft = 0;
        foreach (str_split($bytes) as $b) {
            $buffer = ($buffer << 8) | ord($b);
            $bitsLeft += 8;
            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $out .= $this->b32Alphabet[($buffer >> $bitsLeft) & 0x1F];
            }
        }
        if ($bitsLeft > 0) {
            $out .= $this->b32Alphabet[($buffer << (5 - $bitsLeft)) & 0x1F];
        }

        return $out;
    }

    protected function base32Decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        $out = '';
        $buffer = 0;
        $bitsLeft = 0;
        foreach (str_split($b32) as $ch) {
            $val = strpos($this->b32Alphabet, $ch);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $out .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $out;
    }
}
