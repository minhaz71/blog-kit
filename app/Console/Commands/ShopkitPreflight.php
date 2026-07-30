<?php

namespace App\Console\Commands;

use App\Support\Preflight;
use Illuminate\Console\Command;

/**
 * Production-readiness report. Exits non-zero when any CRITICAL check fails,
 * so it doubles as a gate (`blogkit:update` runs it first, and it can be a
 * deploy pipeline step).
 */
class ShopkitPreflight extends Command
{
    protected $signature = 'blogkit:preflight';

    protected $description = 'Check production readiness (env, debug, DB user, backups, keys, git).';

    public function handle(): int
    {
        $summary = Preflight::summary();

        $this->table(['Check', 'Status', 'Severity'], array_map(fn ($c) => [
            $c['label'],
            $c['ok'] ? '✅ ok' : '❌ FAIL',
            $c['ok'] ? '' : strtoupper($c['severity']),
        ], $summary['checks']));

        foreach ($summary['checks'] as $c) {
            if (! $c['ok']) {
                $this->line(($c['severity'] === 'critical' ? '<error> CRITICAL </error> ' : '<comment> warning </comment> ').$c['detail']);
            }
        }

        if (! $summary['ok']) {
            $this->newLine();
            $this->error("{$summary['critical']} critical issue(s) must be fixed before this site is production-safe.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info($summary['warnings'] > 0
            ? "Production-safe. {$summary['warnings']} advisory warning(s) above."
            : 'All checks passed — production-ready.');

        return self::SUCCESS;
    }
}
