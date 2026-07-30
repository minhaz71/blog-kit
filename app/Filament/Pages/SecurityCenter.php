<?php

namespace App\Filament\Pages;

use App\Models\BlockedIp;
use App\Models\FirewallLog;
use App\Models\LoginAttempt;
use App\Models\SecurityEvent;
use App\Models\ThreatIntelIp;
use App\Services\Security\DependencyAudit;
use App\Services\Security\SecurityAudit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Wordfence-style security overview: hardening score, blocked-attack stats,
 * the posture audit checklist, threat-feed + dependency status, top
 * offenders, and a live event feed — with one-click scan / blocklist / CVE
 * actions. Read models only; actions shell out to the security commands.
 */
class SecurityCenter extends Page
{
    use \App\Filament\Concerns\GatedByPermission;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Security';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Security Center';

    protected string $view = 'filament.pages.security-center';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scan')
                ->label('Run malware scan')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->action(function (): void {
                    Artisan::call('security:scan');
                    Notification::make()->title('Malware scan complete')->body(trim(Artisan::output()))->success()->send();
                }),
            Action::make('updateBlocklist')
                ->label('Update threat blocklist')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('gray')
                ->action(function (): void {
                    Artisan::call('security:update-blocklist');
                    Notification::make()->title('Threat blocklist updated')->body(trim(Artisan::output()))->success()->send();
                }),
            Action::make('auditDeps')
                ->label('Scan dependencies')
                ->icon(Heroicon::OutlinedCube)
                ->color('gray')
                ->action(function (): void {
                    Artisan::call('security:audit-dependencies');
                    Notification::make()->title('Dependency scan complete')->body(trim(Artisan::output()))->success()->send();
                }),
            Action::make('baseline')
                ->label('Rebuild integrity baseline')
                ->icon(Heroicon::OutlinedFingerPrint)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Records the current file hashes as the trusted baseline. Only do this on a clean, known-good site.')
                ->action(function (): void {
                    $count = app(\App\Services\Security\MalwareScanner::class)->buildBaseline();
                    Notification::make()->title('Integrity baseline rebuilt')->body("{$count} files hashed.")->success()->send();
                }),
        ];
    }

    protected function getViewData(): array
    {
        $audit = (new SecurityAudit)->run();

        return [
            'audit' => $audit,
            'stats' => [
                'attacks_24h' => FirewallLog::where('created_at', '>=', now()->subDay())->count(),
                'attacks_7d' => FirewallLog::where('created_at', '>=', now()->subDays(7))->count(),
                'threat_ips' => ThreatIntelIp::count(),
                'active_bans' => BlockedIp::where(fn ($q) => $q->whereNull('blocked_until')->orWhere('blocked_until', '>', now()))->count(),
                'failed_logins_24h' => LoginAttempt::where('successful', false)->where('created_at', '>=', now()->subDay())->count(),
            ],
            'topIps' => FirewallLog::select('ip_address', DB::raw('COUNT(*) as hits'))
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('ip_address')->orderByDesc('hits')->limit(8)->get(),
            'topRules' => FirewallLog::select('rule', DB::raw('COUNT(*) as hits'))
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('rule')->orderByDesc('hits')->limit(8)->get(),
            'events' => SecurityEvent::latest('created_at')->limit(15)->get(),
            'dependencies' => (new DependencyAudit)->latest(),
            'threatUpdatedAt' => ThreatIntelIp::max('last_seen_at'),
        ];
    }
}
