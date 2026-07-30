<?php

namespace App\Jobs;

use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StartAiImportBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    /** Max products in the linking catalog (batch products always included). */
    public const CATALOG_LIMIT = 120;

    public function __construct(public AiImportBatch $batch) {}

    public function handle(): void
    {
        // Re-entrancy guard: "Parse now" in the monitor AND the originally
        // queued job can both arrive here — parse exactly once.
        $this->batch->refresh();

        if ($this->batch->total_items > 0 || $this->batch->items()->exists()) {
            AiActivityLog::write($this->batch->id, null, 'parse', 'Parse skipped — this batch was already parsed.', 'info');

            return;
        }

        $path = Storage::disk('local')->path($this->batch->csv_path);

        if (! is_readable($path)) {
            $this->batch->update(['status' => 'failed', 'error' => "CSV not readable: {$this->batch->csv_path}"]);
            AiActivityLog::write($this->batch->id, null, 'parse', "CSV not readable: {$this->batch->csv_path}", 'error');

            return;
        }

        // ── Smart CSV parsing ──────────────────────────────────────
        // Delimiter sniffing (comma/semicolon/tab), BOM strip, header
        // alias mapping, empty-row skip, duplicate-name dedupe, price
        // normalization — so exports from Excel, WooCommerce, Shopify,
        // or Sheets all import cleanly.
        // First real line = the header. Skip a leading reference/comment
        // block (lines starting with #) and blank lines — the sample CSV
        // ships the store's category list as # comments so the admin knows
        // which ids/names to use; those must not be sniffed as the header.
        $probe = fopen($path, 'r');
        $firstLine = '';
        while (($line = fgets($probe)) !== false) {
            $candidate = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $line)); // strip BOM
            $trimmed = trim($candidate);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $firstLine = $candidate;
            break;
        }
        fclose($probe);
        $delimiter = collect([',', ';', "\t"])
            ->sortByDesc(fn ($d) => substr_count($firstLine, $d))
            ->first();

        $aliases = [
            'title' => 'name', 'product' => 'name', 'item_name' => 'name', 'product_title' => 'name',
            'price' => 'regular_price', 'mrp' => 'regular_price', 'list_price' => 'regular_price',
            'offer_price' => 'sale_price', 'discount_price' => 'sale_price', 'special_price' => 'sale_price',
            'description' => 'short_description', 'desc' => 'short_description', 'summary' => 'short_description',
            'specs' => 'specifications', 'specification' => 'specifications', 'attributes' => 'specifications',
            'image' => 'image_link', 'image_url' => 'image_link', 'img' => 'image_link', 'photo' => 'image_link', 'picture' => 'image_link',
            'categories' => 'category', 'manufacturer' => 'brand',
            'category_ids' => 'category_id', 'cat_id' => 'category_id', 'categoryid' => 'category_id',
            'keyword' => 'keywords', 'target_keywords' => 'keywords', 'seo_keywords' => 'keywords', 'focus_keywords' => 'keywords',
        ];

        $handle = fopen($path, 'r');
        $headers = null;
        $rows = [];
        $seen = [];
        $skippedEmpty = 0;
        $skippedDupes = 0;
        $mapped = [];

        while (($cols = fgetcsv($handle, 0, $delimiter)) !== false) {
            // Skip the reference/comment block and blank lines (see probe above).
            $firstCell = ltrim(trim((string) ($cols[0] ?? '')), "\xEF\xBB\xBF");
            if (str_starts_with($firstCell, '#')
                || count(array_filter($cols, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }

            if ($headers === null) {
                // Alias-collision safe: if a file carries BOTH "Title" and
                // "Name", the alias would silently overwrite the canonical
                // column — keep the original header instead and warn.
                $headers = [];

                foreach ($cols as $rawHeader) {
                    $key = str_replace('product_', '', str_replace([' ', '-'], '_', strtolower(trim(self::toUtf8(preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeader))))));

                    if (isset($aliases[$key])) {
                        $target = $aliases[$key];

                        if (in_array($target, $headers, true)) {
                            AiActivityLog::write($this->batch->id, null, 'parse',
                                "Column \"{$key}\" was NOT mapped to \"{$target}\" — the file already has a \"{$target}\" column. Kept as \"{$key}\".", 'warning');
                        } else {
                            $mapped[] = "{$key}→{$target}";
                            $key = $target;
                        }
                    }

                    // Duplicate header names get numeric suffixes, never dropped.
                    $unique = $key;
                    $n = 2;
                    while (in_array($unique, $headers, true)) {
                        $unique = "{$key}_{$n}";
                        $n++;
                    }

                    $headers[] = $unique;
                }

                continue;
            }

            $row = array_combine($headers, array_pad(array_slice($cols, 0, count($headers)), count($headers), ''));
            // Sanitize to valid UTF-8 — Excel/Windows CSVs are often
            // Windows-1252/Latin-1; invalid bytes otherwise break json_encode
            // when the row is stored on the model.
            $row = array_map(fn ($v) => self::toUtf8(trim((string) $v)), $row);
            $name = $row['name'] ?? '';

            if ($name === '' || implode('', $row) === '') {
                $skippedEmpty++;

                continue;
            }

            // Same product name twice in one file → keep the first.
            $nameKey = mb_strtolower($name);
            if (isset($seen[$nameKey])) {
                $skippedDupes++;

                continue;
            }
            $seen[$nameKey] = true;

            // Normalize prices: "AED 1,299.00" → 1299.00
            foreach (['regular_price', 'sale_price'] as $priceKey) {
                if (($row[$priceKey] ?? '') !== '') {
                    $row[$priceKey] = preg_replace('/[^0-9.]/', '', str_replace(',', '', $row[$priceKey]));
                }
            }

            $rows[] = $row;
        }

        $delimiterName = match ($delimiter) { ';' => 'semicolon', "\t" => 'tab', default => 'comma' };
        AiActivityLog::write($this->batch->id, null, 'parse',
            "Smart parse: {$delimiterName}-delimited, ".count($rows).' valid rows'
            .($mapped !== [] ? ', columns mapped: '.implode(', ', array_unique($mapped)) : '')
            .($skippedEmpty > 0 ? ", {$skippedEmpty} empty row(s) skipped" : '')
            .($skippedDupes > 0 ? ", {$skippedDupes} duplicate name(s) skipped" : '').'.');

        fclose($handle);

        if (count($rows) < 2) {
            $this->batch->update([
                'status' => 'failed',
                'error' => 'At least 2 products are required per batch — internal linking and market comparison need more than one product.',
            ]);
            AiActivityLog::write($this->batch->id, null, 'parse', 'Batch rejected: only '.count($rows).' product row(s) found — minimum is 2.', 'error');

            return;
        }

        // Reserve a unique slug per row BEFORE any writing happens, so every
        // product's live URL is known up front. The AI receives these URLs
        // once (in the cached system prompt) and links siblings contextually
        // while writing — no separate linking pass, no repeated token cost.
        // Checks existing products AND other unfinished batches' reservations,
        // so two concurrent imports can't plan links to the same URL.
        $count = count($rows);

        // All-or-nothing: if any row fails to persist, the whole parse rolls
        // back so the batch stays cleanly "pending" and re-runnable (never a
        // half-parsed state that the re-entrancy guard would then lock).
        \Illuminate\Support\Facades\DB::transaction(function () use ($rows, $count): void {
            $used = [];

            foreach ($rows as $row) {
                $slug = $base = Str::slug((string) $row['name']) ?: 'product';
                $suffix = 2;

                while (
                    isset($used[$slug])
                    || Product::withTrashed()->where('slug', $slug)->exists()
                    || \App\Models\AiImportItem::where('reserved_slug', $slug)
                        ->where('batch_id', '!=', $this->batch->id)
                        ->whereHas('batch', fn ($q) => $q->whereNotIn('status', ['completed', 'failed']))
                        ->exists()
                ) {
                    $slug = $base.'-'.$suffix++;
                }

                $used[$slug] = true;
                $this->batch->items()->create(['row' => $row, 'reserved_slug' => $slug]);
            }

            $this->batch->update([
                'status' => 'processing',
                'total_items' => $count,
                'link_catalog' => $this->buildLinkCatalog(),
            ]);
        });

        $catalog = (array) $this->batch->fresh()->link_catalog;

        AiActivityLog::write($this->batch->id, null, 'parse', "CSV parsed — {$count} products queued for writing (processed one by one).", 'success');
        AiActivityLog::write($this->batch->id, null, 'parse',
            'Link catalog built — '.count($catalog)." live URLs ({$count} from this batch, ".(count($catalog) - $count).' already in the store). '
            .'Sent to the AI once per batch inside the cached system prompt — not repeated per product.', 'info');

        // Kick off processing ONLY on the synchronous queue (tests + any
        // sync install). The real runner is `ai:run-batch` (launched detached
        // via BackgroundProcess), which processes items inline one by one.
        // On a database/redis queue with no worker, auto-dispatching here just
        // leaves orphan job rows that never run — and once the inline runner
        // publishes the items, those rows show up in the AI call queue as
        // already-published "pending" calls. So skip them off the sync queue.
        if (config('queue.default') === 'sync') {
            foreach ($this->batch->items()->where('status', 'pending')->pluck('id') as $itemId) {
                WriteAiProduct::dispatch($itemId);
            }
        }
    }

    /**
     * Force a string to valid UTF-8 so it survives JSON encoding on the
     * model. Excel/Windows CSVs are commonly Windows-1252 (superset of
     * Latin-1); when a value isn't already valid UTF-8 we convert from that,
     * then hard-strip any bytes still invalid as a last resort.
     */
    public static function toUtf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');

        if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        // Still bad → drop the invalid bytes rather than fail the import.
        return (string) @iconv('UTF-8', 'UTF-8//IGNORE', $value);
    }

    /**
     * Batch products first (name + reserved URL), then category pages and
     * recent guides (so product copy can link UP to its category and OUT to
     * buying guides — the semantic link flow, not just sibling products),
     * then the store's newest published products, capped at CATALOG_LIMIT
     * total. Stored on the batch so the system prompt stays byte-identical
     * across items (cacheable).
     */
    protected function buildLinkCatalog(): array
    {
        $catalog = $this->batch->items()->orderBy('id')->get()
            ->map(fn ($item) => [
                'name' => (string) ($item->row['name'] ?? ''),
                'url' => \App\Support\Permalinks::product($item->reserved_slug),
            ])
            ->all();

        $categories = \App\Models\Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn ($category) => ['name' => $category->name, 'url' => $category->url(), 'type' => 'category'])
            ->all();

        $guides = \App\Models\Post::query()
            ->published()
            ->latest('published_at')
            ->limit(15)
            ->get(['id', 'title', 'slug'])
            ->map(fn ($post) => ['name' => $post->title, 'url' => $post->url(), 'type' => 'guide'])
            ->all();

        $existing = Product::query()
            ->where('status', 'published')
            ->latest('id')
            ->limit(max(0, self::CATALOG_LIMIT - count($catalog) - count($categories) - count($guides)))
            ->get(['name', 'slug'])
            ->map(fn (Product $product) => ['name' => $product->name, 'url' => $product->url()])
            ->all();

        return array_merge($catalog, $categories, $guides, $existing);
    }
}
