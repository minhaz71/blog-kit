<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Console\Command;

class SeoCheckMissingMetaCommand extends Command
{
    protected $signature = 'seo:check-missing-meta';

    protected $description = 'Report content items with missing SEO title or meta description.';

    public function handle(): int
    {
        $issues = [];

        foreach ([
            [Product::class, 'name'],
            [Category::class, 'name'],
            [Post::class, 'title'],
            [Page::class, 'title'],
        ] as [$model, $labelField]) {
            $records = $model::query()->with('seoMeta')->get();
            foreach ($records as $record) {
                $meta = $record->seoMeta;
                if (! $meta || blank($meta->title)) {
                    $issues[] = [class_basename($model), $record->id, $record->{$labelField}, 'missing_title'];
                } elseif (blank($meta->description)) {
                    $issues[] = [class_basename($model), $record->id, $record->{$labelField}, 'missing_description'];
                }
            }
        }

        if (empty($issues)) {
            $this->info('No SEO metadata gaps found.');

            return self::SUCCESS;
        }

        $this->warn('Found '.count($issues).' items missing SEO metadata:');
        $this->table(['Type', 'ID', 'Name', 'Issue'], $issues);

        return self::SUCCESS;
    }
}
