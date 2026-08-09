<?php

namespace App\Support;

/**
 * A persistent, file-based record of the last (or running) self-update, so the
 * admin sees the outcome WITHOUT SSH — the whole point for owners who don't
 * touch the VPS. File-based (not DB) on purpose: it must survive maintenance
 * mode and config/route cache rebuilds that happen mid-update, and it must be
 * writable even if the database is briefly unavailable.
 *
 * Shape:
 *   state      idle|running|success|failed
 *   step       short label of the current/last step
 *   message    human summary (error text on failure)
 *   from,to    version strings
 *   started_at,finished_at  ISO-8601
 *   log        rolling tail of the update output (last ~200 lines)
 */
class UpdateStatus
{
    protected static function path(): string
    {
        return storage_path('app/update-status.json');
    }

    /** @return array<string,mixed> */
    public static function get(): array
    {
        $file = self::path();
        if (! is_file($file)) {
            return ['state' => 'idle'];
        }

        $data = json_decode((string) @file_get_contents($file), true);

        return is_array($data) ? $data : ['state' => 'idle'];
    }

    public static function begin(?string $from): void
    {
        self::write([
            'state' => 'running',
            'step' => 'Starting',
            'message' => 'Update started.',
            'from' => $from,
            'to' => null,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'log' => '',
        ]);
    }

    public static function step(string $label): void
    {
        $s = self::get();
        $s['state'] = 'running';
        $s['step'] = $label;
        self::write($s);
    }

    /** Append a chunk of raw command output to the rolling log tail. */
    public static function appendLog(string $chunk): void
    {
        $chunk = trim($chunk);
        if ($chunk === '') {
            return;
        }

        $s = self::get();
        $log = (string) ($s['log'] ?? '');
        $lines = preg_split('/\r?\n/', $log.($log === '' ? '' : "\n").$chunk) ?: [];
        // Keep only the last 200 lines so the file stays small.
        $s['log'] = implode("\n", array_slice($lines, -200));
        self::write($s);

        // Also tee to a dedicated, greppable log file.
        @file_put_contents(storage_path('logs/update.log'), $chunk."\n", FILE_APPEND);
    }

    public static function finish(bool $ok, string $message, ?string $to = null): void
    {
        $s = self::get();
        $s['state'] = $ok ? 'success' : 'failed';
        $s['message'] = $message;
        $s['to'] = $to;
        $s['finished_at'] = now()->toIso8601String();
        self::write($s);
    }

    /** @param array<string,mixed> $data */
    protected static function write(array $data): void
    {
        @file_put_contents(self::path(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
