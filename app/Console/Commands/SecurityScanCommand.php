<?php

namespace App\Console\Commands;

use App\Services\Security\MalwareScanner;
use App\Services\Security\SecurityAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SecurityScanCommand extends Command
{
    protected $signature = 'security:scan';

    protected $description = 'Scan app files and public uploads for suspicious patterns and modified core files.';

    public function handle(MalwareScanner $scanner, SecurityAlertService $alerts): int
    {
        $this->info('Running file integrity + malware scan...');
        $result = $scanner->scan();

        // Stamp the run so SecurityAudit knows a clean scan happened even
        // when it produced zero rows. Store a STRING, never a Carbon object —
        // the file/database cache store rehydrates objects to
        // __PHP_Incomplete_Class.
        Cache::put('security.last_malware_scan', now()->toIso8601String(), now()->addDays(30));

        $this->info("Scanned {$result['scanned']} files, {$result['issues']} issues found.");

        if ($result['issues'] > 0) {
            $alerts->record('malware', "{$result['issues']} malware/integrity issue(s) found", 'critical',
                'The scanner flagged suspicious files or changes to core files. Review them in the Security Center → File scan.',
                meta: ['issues' => $result['issues'], 'scanned' => $result['scanned']]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
