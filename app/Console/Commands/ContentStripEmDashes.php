<?php

namespace App\Console\Commands;

use App\Models\Faq;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SeoMeta;
use App\Services\Ai\ContentReviewer;
use Illuminate\Console\Command;

/**
 * One-shot store style enforcement: rewrites em/en dashes across ALL
 * customer-facing content (products, posts, SEO metas, FAQs, image text)
 * using the same rules the AI pipeline applies (numeric ranges keep a
 * hyphen, everything else becomes a comma pause). New AI output is already
 * gated; this cleans content written before the rule or imported/recovered
 * from elsewhere. Safe to re-run any time.
 */
class ContentStripEmDashes extends Command
{
    protected $signature = 'content:strip-em-dashes {--dry-run : Count what would change without saving}';

    protected $description = 'Rewrite em/en dashes in all published content (products, posts, SEO meta, FAQs, image text)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $targets = [
            [Product::class, ['name', 'short_description', 'description']],
            [Post::class, ['title', 'excerpt', 'content']],
            [SeoMeta::class, ['title', 'description']],
            [Faq::class, ['question', 'answer']],
            [ProductImage::class, ['alt', 'title', 'caption']],
        ];

        foreach ($targets as [$model, $columns]) {
            $query = $model::query()->where(function ($q) use ($columns) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', '%—%')->orWhere($column, 'like', '%–%');
                }
            });

            // Include trashed rows where the model supports it — restoring a
            // trashed product must not resurrect banned punctuation.
            if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model), true)) {
                $query->withTrashed();
            }

            $count = 0;

            $query->each(function ($record) use ($columns, $dry, &$count) {
                $changes = [];

                foreach ($columns as $column) {
                    $value = $record->{$column};

                    if (is_string($value) && preg_match('/[—–]/u', $value)) {
                        $changes[$column] = ContentReviewer::stripEmDashes($value);
                    }
                }

                if ($changes !== []) {
                    $count++;

                    if (! $dry) {
                        $record->forceFill($changes)->saveQuietly();
                    }
                }
            });

            $label = class_basename($model);
            $this->line($dry
                ? "{$label}: {$count} record(s) would be cleaned"
                : "{$label}: cleaned {$count} record(s)");
        }

        if (! $dry) {
            $this->info('Done. All customer-facing text now follows the no-em-dash rule.');
        }

        return self::SUCCESS;
    }
}
