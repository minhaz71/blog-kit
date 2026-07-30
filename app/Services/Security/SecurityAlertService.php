<?php

namespace App\Services\Security;

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Central intrusion-alerting: records a curated SecurityEvent and, for
 * high/critical severities, emails the store's security recipients (like
 * Wordfence's real-time alerts). Recording never throws — security logging
 * must not take the site down.
 */
class SecurityAlertService
{
    /** Severities that trigger an immediate email when alerts are enabled. */
    public const NOTIFY_SEVERITIES = ['high', 'critical'];

    public function record(
        string $type,
        string $title,
        string $severity = 'info',
        ?string $description = null,
        ?string $ip = null,
        ?string $country = null,
        ?int $userId = null,
        array $meta = [],
    ): ?SecurityEvent {
        try {
            $event = SecurityEvent::create([
                'type' => $type,
                'severity' => in_array($severity, SecurityEvent::SEVERITIES, true) ? $severity : 'info',
                'title' => mb_substr($title, 0, 255),
                'description' => $description,
                'ip_address' => $ip,
                'country' => $country,
                'user_id' => $userId,
                'meta' => $meta ?: null,
                'created_at' => now(),
            ]);

            if (in_array($event->severity, self::NOTIFY_SEVERITIES, true)) {
                $this->notify($event);
            }

            return $event;
        } catch (\Throwable $e) {
            Log::warning('SecurityAlertService failed: '.$e->getMessage());

            return null;
        }
    }

    protected function notify(SecurityEvent $event): void
    {
        if (! setting('security.alerts_enabled', true)) {
            return;
        }

        // Throttle: at most one email per event type per 10 minutes, so an
        // attack flood can't turn into an inbox flood.
        $throttleKey = "security.alert.{$event->type}";
        if (\Illuminate\Support\Facades\Cache::get($throttleKey)) {
            return;
        }
        \Illuminate\Support\Facades\Cache::put($throttleKey, true, now()->addMinutes(10));

        foreach ($this->recipients() as $email) {
            try {
                Mail::raw($this->body($event), function ($message) use ($email, $event) {
                    $message->to($email)->subject('[Security] '.ucfirst($event->severity).': '.$event->title);
                });
            } catch (\Throwable $e) {
                Log::warning('Security alert email failed: '.$e->getMessage());
            }
        }

        $event->update(['notified' => true]);
    }

    /** @return array<int,string> */
    protected function recipients(): array
    {
        $explicit = array_filter(array_map('trim', explode(',', (string) setting('security.alert_emails', ''))));

        if ($explicit !== []) {
            return $explicit;
        }

        // Fall back to Super Admins.
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))
            ->pluck('email')
            ->all();
    }

    protected function body(SecurityEvent $event): string
    {
        return implode("\n", array_filter([
            $event->title,
            '',
            $event->description,
            '',
            'Severity: '.strtoupper($event->severity),
            $event->ip_address ? 'IP: '.$event->ip_address.($event->country ? " ({$event->country})" : '') : null,
            'Time: '.$event->created_at->toDayDateTimeString(),
            '',
            'Review it in the admin: '.rtrim((string) config('app.url'), '/').'/admin/security-center',
        ]));
    }
}
