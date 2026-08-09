<?php

namespace App\Console\Commands;

use App\Services\Security\DependencyAudit;
use App\Services\Security\SecurityAlertService;
use Illuminate\Console\Command;

class SecurityAuditDependencies extends Command
{
    protected $signature = 'security:audit-dependencies';

    protected $description = 'Scan installed Composer packages for known security advisories (CVEs)';

    public function handle(DependencyAudit $audit, SecurityAlertService $alerts): int
    {
        $result = $audit->run();

        if ($result['error']) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        $via = ($result['source'] ?? 'composer') === 'packagist'
            ? ' (via Packagist API — composer not available on this server)'
            : '';

        if ($result['advisories'] === 0) {
            $this->info('✅ No known vulnerabilities in installed dependencies.'.$via
                .($result['abandoned'] > 0 ? " ({$result['abandoned']} abandoned package(s) — consider replacing.)" : ''));

            return self::SUCCESS;
        }

        $this->warn("⚠ {$result['advisories']} dependency advisory(ies) found{$via}:");
        foreach ($result['packages'] as $p) {
            $this->line("  [{$p['severity']}] {$p['package']}: {$p['title']}".($p['cve'] ? " ({$p['cve']})" : ''));
        }

        $alerts->record('dependency_vuln', "{$result['advisories']} vulnerable dependency(ies) detected", 'high',
            'Published security advisories affect installed packages. Run `composer update` for the affected packages.',
            meta: ['packages' => array_column($result['packages'], 'package'), 'source' => $result['source'] ?? 'composer']);

        return self::SUCCESS;
    }
}
