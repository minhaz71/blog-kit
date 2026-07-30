<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Empties the trash: permanently deletes soft-deleted products, orders,
 * posts and pages that have been sitting in the trash longer than the
 * retention window. Scheduled daily; admins can also force-delete instantly
 * from each list's "Trashed" view.
 */
class TrashPurgeCommand extends Command
{
    protected $signature = 'trash:purge {--days=90 : Purge items trashed more than this many days ago} {--dry-run : Report what would be purged without deleting}';

    protected $description = 'Permanently delete trashed products, orders, posts and pages older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $dry = (bool) $this->option('dry-run');

        $models = [
            'products' => Product::class,
            'orders' => Order::class,
            'posts' => Post::class,
            'pages' => Page::class,
        ];

        foreach ($models as $label => $class) {
            $query = $class::onlyTrashed()->where('deleted_at', '<', $cutoff);
            $count = $query->count();

            if ($count === 0) {
                $this->line("{$label}: nothing to purge");

                continue;
            }

            if ($dry) {
                $this->line("{$label}: {$count} would be purged");

                continue;
            }

            // forceDelete per model (not mass) so model events + relation
            // cleanup (cascades, image files via observers) still fire.
            $query->each(fn ($record) => $record->forceDelete());

            $this->info("{$label}: purged {$count} (trashed before {$cutoff->toDateString()})");
        }

        return self::SUCCESS;
    }
}
