<?php

namespace App\Console\Commands;

use App\Services\Security\ThreatIntelligence;
use Illuminate\Console\Command;

class SecurityUpdateBlocklist extends Command
{
    protected $signature = 'security:update-blocklist';

    protected $description = 'Refresh the real-time threat-intelligence IP blocklist from public feeds';

    public function handle(ThreatIntelligence $intel): int
    {
        $this->info('Fetching threat-intelligence feeds…');
        $result = $intel->refresh();

        foreach ($result['feeds'] as $feed => $count) {
            $this->line("  {$feed}: {$count} IPs");
        }

        $this->info("Blocklist updated — {$result['imported']} active threat IPs (pruned {$result['skipped']} stale).");

        return $result['imported'] > 0 ? self::SUCCESS : self::FAILURE;
    }
}
