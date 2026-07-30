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
    public static function artisan(array $args): bool
    {
        // Not meaningful under the test runner.
        if (app()->runningUnitTests()) {
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
