<?php

namespace App\Services\Security;

use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use Illuminate\Support\Facades\Cache;

/**
 * Login protection: attempt logging, lockouts after repeated failures,
 * and common-username blocking.
 */
class LoginSecurityService
{
    public function recordAttempt(?string $email, string $ip, ?string $userAgent, bool $successful, bool $adminArea = false): void
    {
        LoginAttempt::create([
            'email' => $email,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? str($userAgent)->limit(495, '')->toString() : null,
            'successful' => $successful,
            'is_admin_area' => $adminArea,
        ]);

        if ($successful) {
            Cache::forget("login.failures.{$ip}");

            return;
        }

        $key = "login.failures.{$ip}";
        $failures = (int) Cache::increment($key);

        if ($failures === 1) {
            Cache::put($key, 1, 900); // 15-minute window
        }

        $maxAttempts = (int) setting('security.max_login_attempts', 5);

        if ($failures >= $maxAttempts) {
            $lockMinutes = (int) setting('security.lockout_minutes', 30);
            BlockedIp::block($ip, 'Too many failed login attempts', now()->addMinutes($lockMinutes));
        }
    }

    public function isLockedOut(string $ip): bool
    {
        return BlockedIp::isBlocked($ip);
    }

    /** Usernames/emails that are never allowed to authenticate. */
    public function isBlockedUsername(string $identifier): bool
    {
        $blocked = (array) setting('security.blocked_usernames', ['admin', 'administrator', 'root', 'test', 'demo']);
        $local = strtolower(strtok($identifier, '@') ?: $identifier);

        return in_array($local, array_map('strtolower', $blocked), true);
    }

    /**
     * CUSTOMER password rules (register / password reset / account change).
     * Owner decision 2026-07-13: shoppers only need a simple 6+ character
     * password — forcing symbols/mixed-case on customers costs signups.
     * STAFF strength is separate and unaffected: StaffUserResource enforces
     * a 10-character minimum on every admin account.
     */
    public function passwordRules(): array
    {
        return ['required', 'string', 'confirmed', \Illuminate\Validation\Rules\Password::min(6)];
    }
}
