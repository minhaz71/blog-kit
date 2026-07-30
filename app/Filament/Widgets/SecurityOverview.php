<?php

namespace App\Filament\Widgets;

use App\Models\BlockedIp;
use App\Models\FileScanResult;
use App\Models\FirewallLog;
use App\Models\LoginAttempt;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SecurityOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $since = now()->subDay();

        $firewall24h = FirewallLog::query()->where('created_at', '>=', $since)->count();
        $failedLogins24h = LoginAttempt::query()->where('created_at', '>=', $since)->where('successful', false)->count();
        $blocked = BlockedIp::query()->count();
        $openScans = FileScanResult::query()->where('is_resolved', false)->count();

        return [
            Stat::make('Firewall hits 24h', (string) $firewall24h)
                ->description('Blocked requests')
                ->color($firewall24h > 50 ? 'danger' : 'gray'),
            Stat::make('Failed logins 24h', (string) $failedLogins24h)
                ->description('Across all forms')
                ->color($failedLogins24h > 20 ? 'warning' : 'gray'),
            Stat::make('IPs blocked', (string) $blocked)
                ->description('Currently on the blocklist')
                ->color('gray'),
            Stat::make('Open scan alerts', (string) $openScans)
                ->description('Unresolved file scanner findings')
                ->color($openScans > 0 ? 'danger' : 'success'),
        ];
    }
}
