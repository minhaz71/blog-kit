<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Services\Performance\LiteSpeedPurger;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

/**
 * Manual, on-demand link audit — distinct from seo:scan-links (which only
 * indexes internal product/post links for the reactive Broken Links report).
 * This one greps every product/category/post/page for:
 *   - stale localhost/127.0.0.1 links left over from local editing
 *   - (with --http) any absolute or relative link that no longer resolves
 * --fix rewrites the stale-host links in place (to relative paths); it never
 * touches HTTP-status issues since a dead link can't be auto-repaired.
 */
class SeoCheckLinksCommand extends Command
{
    protected const LINK_PATTERN = '~<a\s[^>]*?href=(["\'])(.*?)\1[^>]*>~is';

    protected $signature = 'seo:check-links
        {--http : Also send a live request to every link and flag non-2xx/3xx responses (slower)}
        {--fix : Rewrite stale localhost links to relative paths}
        {--force : Skip the confirmation prompt when using --fix}';

    protected $description = 'Scan product/category/post/page content and canonical URLs for stale localhost links and, with --http, dead links.';

    /** @var array<int, array{0: string, 1: string, 2: string, 3: string}> */
    protected array $issues = [];

    /** @var array<int, array{model: Model, target: Model, field: string, from: string, to: string}> */
    protected array $fixes = [];

    public function handle(): int
    {
        $this->scan(Product::query()->published(), 'name', ['description', 'short_description']);
        $this->scan(Category::query()->where('is_active', true), 'name', ['description', 'content_block']);
        $this->scan(Post::query()->published(), 'title', ['content']);
        $this->scan(Page::query()->published(), 'title', ['content']);

        if ($this->issues === []) {
            $this->info('No stale-host or broken links found.');

            return self::SUCCESS;
        }

        $this->warn('Found '.count($this->issues).' link issue(s):');
        $this->table(['Item', 'Field', 'URL', 'Issue'], $this->issues);

        if (! $this->option('fix')) {
            return self::SUCCESS;
        }

        if ($this->fixes === []) {
            $this->info('Nothing auto-fixable (only stale localhost links are rewritten — HTTP issues need a manual look).');

            return self::SUCCESS;
        }

        $this->warn('This will rewrite '.count($this->fixes).' link(s) in the fields above to relative paths.');

        if (! $this->option('force') && ! $this->confirm('Continue?')) {
            $this->info('Fix cancelled.');

            return self::SUCCESS;
        }

        $this->applyFixes();

        return self::SUCCESS;
    }

    /** @param array<int, string> $htmlFields */
    protected function scan($query, string $labelField, array $htmlFields): void
    {
        $query->with('seoMeta')->chunkById(200, function ($records) use ($labelField, $htmlFields): void {
            foreach ($records as $record) {
                $label = class_basename($record).' #'.$record->id.' ('.$record->{$labelField}.')';

                if ($meta = $record->seoMeta) {
                    if ($url = $meta->canonical_url) {
                        $this->checkUrl($url, $record, $meta, 'canonical_url', $label);
                    }
                }

                foreach ($htmlFields as $field) {
                    foreach ($this->extractLinks((string) $record->{$field}) as $url) {
                        $this->checkUrl($url, $record, $record, $field, $label);
                    }
                }
            }
        });
    }

    /** @return array<int, string> */
    protected function extractLinks(string $html): array
    {
        if (! preg_match_all(self::LINK_PATTERN, $html, $matches)) {
            return [];
        }

        return $matches[2];
    }

    /** $target is whichever model actually owns $field ($record itself, or its seoMeta). */
    protected function checkUrl(string $url, Model $record, Model $target, string $field, string $label): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        $httpTarget = $url;

        if ($host === null) {
            if (! str_starts_with($url, '/')) {
                return; // mailto:, tel:, #anchor — not a real link to check
            }
            if (! $this->option('http')) {
                return; // relative links only matter for the live check
            }
            $httpTarget = rtrim((string) config('app.url'), '/').$url;
        } elseif (str_contains($host, 'localhost') || str_starts_with($host, '127.0.0.1')) {
            $this->issues[] = [$label, $field, $url, 'stale localhost link'];

            $path = (string) parse_url($url, PHP_URL_PATH);
            $query = parse_url($url, PHP_URL_QUERY);
            $this->fixes[] = [
                'model' => $record,
                'target' => $target,
                'field' => $field,
                'from' => $url,
                'to' => ($path === '' ? '/' : $path).($query ? "?{$query}" : ''),
            ];

            return;
        }

        if (! $this->option('http')) {
            return;
        }

        try {
            $status = Http::timeout(8)->head($httpTarget)->status();
            if ($status >= 400) {
                $this->issues[] = [$label, $field, $url, "HTTP {$status}"];
            }
        } catch (\Throwable) {
            $this->issues[] = [$label, $field, $url, 'unreachable'];
        }
    }

    protected function applyFixes(): void
    {
        LiteSpeedPurger::beginBatch();

        try {
            $dirty = [];

            foreach ($this->fixes as $fix) {
                $target = $fix['target'];
                $target->{$fix['field']} = str_replace($fix['from'], $fix['to'], (string) $target->{$fix['field']});
                $dirty[spl_object_id($target)] = $target;
            }

            foreach ($dirty as $target) {
                $target->save();
            }

            $this->info('Fixed '.count($this->fixes).' link(s) across '.count($dirty).' record(s).');
        } finally {
            LiteSpeedPurger::endBatch();
        }
    }
}
