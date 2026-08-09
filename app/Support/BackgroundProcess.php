<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Fire-and-forget a detached artisan command so long work (like an AI batch)
 * runs to completion in its own OS process — no queue worker required, and
 * the web request returns immediately.
 *
 * On Unix (macOS/Linux) `nohup … &` reparents the child to init so it
 * survives the request. Returns true if the process was launched.
 */
class BackgroundProcess
{
    /**
     * Can this host actually spawn a detached process? Managed PHP (many
     * CyberPanel/OpenLiteSpeed, cPanel, shared hosts) disable proc_open/exec via
     * `disable_functions`, in which case a "launch" silently does nothing. Detect
     * that up front so callers can fall back (queue/sync) or show the SSH command
     * instead of a false "started".
     */
    public static function canSpawn(): bool
    {
        if (\function_exists('app') && app()->runningUnitTests()) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        // Symfony Process needs proc_open; nohup backgrounding needs a shell.
        foreach (['proc_open'] as $fn) {
            if (! \function_exists($fn) || in_array($fn, $disabled, true)) {
                return false;
            }
        }

        return true;
    }

    public static function artisan(array $args): bool
    {
        // Not meaningful under the test runner, or where the host forbids it.
        if (! self::canSpawn()) {
            return false;
        }

        $php = PHP_BINARY ?: 'php';
        $artisan = base_path('artisan');
        $logFile = storage_path('logs/background.log');

        $parts = array_map('escapeshellarg', array_merge([$php, $artisan], $args));

        // nohup + & → the shell backgrounds it and returns at once; the child
        // keeps running detached from this request.
        $command = 'nohup '.implode(' ', $parts).' >> '.escapeshellarg($logFile).' 2>&1 &';

        try {
            $process = Process::fromShellCommandline($command, base_path());
            $process->setTimeout(5);
            $process->run(); // returns immediately because of the trailing &

            return true;
        } catch (\Throwable $e) {
            Log::warning('BackgroundProcess launch failed: '.$e->getMessage());

            return false;
        }
    }
}
