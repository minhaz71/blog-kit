<?php

namespace App\Console\Commands;

use App\Models\Cart;
use App\Support\AbandonedCartFlow;
use Illuminate\Console\Command;

class ClearExpiredCartsCommand extends Command
{
    protected $signature = 'ecommerce:clear-expired-carts {--days=30 : Delete carts older than this many days}';

    protected $description = 'Delete abandoned/expired guest carts older than N days.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);
        $stageCount = AbandonedCartFlow::stageCount();

        $count = Cart::query()
            ->where('status', '!=', 'converted')
            ->where('updated_at', '<', $cutoff)
            // Preserve carts still moving through the reminder sequence, so we
            // never delete a reachable lead before its last (e.g. 1-month)
            // reminder fires. Delete only unreachable carts or finished ones.
            ->where(function ($q) use ($stageCount) {
                $q->where(fn ($q2) => $q2->whereNull('email')->whereNull('user_id'))
                    ->orWhere('reminder_stage', '>=', $stageCount);
            })
            ->delete();

        $this->info("Deleted {$count} expired cart(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
