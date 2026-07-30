<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Toggle "site under construction" mode from the CLI — the guaranteed escape
 * hatch so an operator can never be locked out (turn it off over SSH without
 * needing to log into the admin first).
 *
 *   php artisan blogkit:maintenance on
 *   php artisan blogkit:maintenance off
 *   php artisan blogkit:maintenance status
 */
class MaintenanceModeCommand extends Command
{
    protected $signature = 'blogkit:maintenance {state=status : on | off | status}';

    protected $description = 'Turn the storefront maintenance/"under construction" mode on or off (CLI escape hatch).';

    public function handle(): int
    {
        $state = strtolower((string) $this->argument('state'));

        if (! in_array($state, ['on', 'off', 'status'], true)) {
            $this->error('Usage: php artisan blogkit:maintenance {on|off|status}');

            return self::FAILURE;
        }

        if ($state === 'status') {
            $this->line('Maintenance mode is currently: '.(setting('general.maintenance_mode') ? '<fg=yellow>ON</>' : '<fg=green>OFF</>'));

            return self::SUCCESS;
        }

        Setting::set('general.maintenance_mode', $state === 'on');
        Cache::forget('settings.general');

        $this->info($state === 'on'
            ? 'Maintenance mode is now ON — customers see the "under construction" page; staff still have full access.'
            : 'Maintenance mode is now OFF — the store is live for everyone.');

        return self::SUCCESS;
    }
}
