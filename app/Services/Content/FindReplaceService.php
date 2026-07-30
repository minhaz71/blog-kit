<?php

namespace App\Services\Content;

use App\Models\Category;
use App\Models\ContentReplaceBatch;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Services\Performance\PageCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

/**
 * Site-wide Find & Replace across CONTENT/prose fields only.
 *
 * Safety model (why this can't harm the DB or the site):
 *  - Target (table, column) pairs come ONLY from the hard-coded SCOPES
 *    whitelist below — never from user input. Names, titles, slugs, prices,
 *    SKUs and every numeric/config column are simply not in the list, so they
 *    can never be touched. The admin only picks WHICH whitelisted scopes to run.
 *  - Replacement happens in PHP per-row (fetch → regex replace → update by id),
 *    inside a transaction, so a failure rolls back cleanly.
 *  - Every changed field is snapshotted (old + new) into content_replace_items,
 *    so any batch reverts EXACTLY. Revert also refuses to clobber a field that
 *    has been edited since (current value must still equal what we wrote).
 *  - Dry run reads only; it writes nothing.
 */
class FindReplaceService
{
    /**
     * The complete whitelist. key => config. Only these columns are ever read
     * or written. `columns` are prose fields; `where` narrows polymorphic rows.
     */
    public const SCOPES = [
        // ── Core content ─────────────────────────────────────────────
        'products' => ['label' => 'Products — short & long description', 'group' => 'Content', 'type' => 'Product',
            'table' => 'products', 'columns' => ['short_description', 'description']],
        'categories' => ['label' => 'Categories — description & content block', 'group' => 'Content', 'type' => 'Category',
            'table' => 'categories', 'columns' => ['description', 'content_block']],
        'posts' => ['label' => 'Blog posts — excerpt & body', 'group' => 'Content', 'type' => 'Blog post',
            'table' => 'posts', 'columns' => ['excerpt', 'content']],
        'pages' => ['label' => 'Pages — body', 'group' => 'Content', 'type' => 'Page',
            'table' => 'pages', 'columns' => ['content']],
        'faqs' => ['label' => 'FAQs — question & answer', 'group' => 'Content', 'type' => 'FAQ',
            'table' => 'faqs', 'columns' => ['question', 'answer']],
        'variations' => ['label' => 'Variations — SEO title & description', 'group' => 'Content', 'type' => 'Variation',
            'table' => 'product_variations', 'columns' => ['seo_title', 'seo_description']],

        // ── SEO meta (polymorphic seo_meta rows, split by owner type) ─
        'product_seo' => ['label' => 'Product SEO — title, meta & social', 'group' => 'SEO', 'type' => 'Product SEO',
            'table' => 'seo_meta', 'columns' => ['title', 'description', 'og_title', 'og_description', 'twitter_title', 'twitter_description'],
            'where' => ['metable_type' => Product::class], 'parent_table' => 'products', 'parent_key' => 'metable_id'],
        'category_seo' => ['label' => 'Category SEO — title, meta & social', 'group' => 'SEO', 'type' => 'Category SEO',
            'table' => 'seo_meta', 'columns' => ['title', 'description', 'og_title', 'og_description', 'twitter_title', 'twitter_description'],
            'where' => ['metable_type' => Category::class], 'parent_table' => 'categories', 'parent_key' => 'metable_id'],
        'post_seo' => ['label' => 'Blog SEO — title, meta & social', 'group' => 'SEO', 'type' => 'Blog SEO',
            'table' => 'seo_meta', 'columns' => ['title', 'description', 'og_title', 'og_description', 'twitter_title', 'twitter_description'],
            'where' => ['metable_type' => Post::class], 'parent_table' => 'posts', 'parent_key' => 'metable_id'],
        'page_seo' => ['label' => 'Page SEO — title, meta & social', 'group' => 'SEO', 'type' => 'Page SEO',
            'table' => 'seo_meta', 'columns' => ['title', 'description', 'og_title', 'og_description', 'twitter_title', 'twitter_description'],
            'where' => ['metable_type' => Page::class], 'parent_table' => 'pages', 'parent_key' => 'metable_id'],

        // ── Secondary (opt-in) ───────────────────────────────────────
        'brands' => ['label' => 'Brands — description', 'group' => 'Other', 'type' => 'Brand',
            'table' => 'brands', 'columns' => ['description']],
        'coupons' => ['label' => 'Coupons — description', 'group' => 'Other', 'type' => 'Coupon',
            'table' => 'coupons', 'columns' => ['description']],
        'shipping_methods' => ['label' => 'Shipping methods — description', 'group' => 'Other', 'type' => 'Shipping method',
            'table' => 'shipping_methods', 'columns' => ['description']],
        'payment_methods' => ['label' => 'Payment methods — description', 'group' => 'Other', 'type' => 'Payment method',
            'table' => 'payment_methods', 'columns' => ['description']],
    ];

    /** How many detail rows the dry-run returns for display (counts stay exact). */
    protected int $previewLimit = 400;

    /** Grouped options for a CheckboxList: key => "Group · label". */
    public function scopeOptions(): array
    {
        $out = [];
        foreach (self::SCOPES as $key => $cfg) {
            $out[$key] = $cfg['group'].' · '.$cfg['label'];
        }

        return $out;
    }

    /** Scope keys enabled by default in the UI (core content + SEO). */
    public function defaultScopeKeys(): array
    {
        return array_keys(array_filter(self::SCOPES, fn ($c) => in_array($c['group'], ['Content', 'SEO'], true)));
    }

    /**
     * Read-only preview: exact totals + a capped list of matches (table,
     * column, record, occurrences, snippet). Writes nothing.
     *
     * @return array{records:int, occurrences:int, truncated:bool, matches:array<int,array>}
     */
    public function dryRun(string $find, array $scopeKeys, array $opts = []): array
    {
        $find = $this->requireFind($find);
        $pattern = $this->pattern($find, $opts);

        $records = 0;
        $occurrences = 0;
        $matches = [];

        foreach ($this->resolvedTargets($scopeKeys) as $t) {
            foreach ($this->rows($t, $find) as $row) {
                $labels = $this->labelsFor($t, (array) $row);
                $rowMatched = false;
                foreach ($t['columns'] as $col) {
                    $value = $row->{$col} ?? null;
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $count = preg_match_all($pattern, (string) $value);
                    if (! $count) {
                        continue;
                    }

                    $rowMatched = true;
                    $occurrences += $count;

                    if (count($matches) < $this->previewLimit) {
                        $matches[] = [
                            'type' => $t['type'],
                            'record' => $labels['label'],
                            'field' => $this->humanColumn($col),
                            'location' => $t['table'].'.'.$col,
                            'occurrences' => $count,
                            'preview' => $this->snippet((string) $value, $pattern),
                        ];
                    }
                }
                if ($rowMatched) {
                    $records++;
                }
            }
        }

        return [
            'records' => $records,
            'occurrences' => $occurrences,
            'truncated' => count($matches) >= $this->previewLimit,
            'matches' => $matches,
        ];
    }

    /**
     * Perform the replacement inside a transaction, snapshotting every change
     * for exact undo. Cache flushes happen after commit and never break the run.
     */
    public function apply(string $find, string $replace, array $scopeKeys, array $opts = [], ?int $userId = null): ContentReplaceBatch
    {
        $find = $this->requireFind($find);
        $pattern = $this->pattern($find, $opts);
        $targets = $this->resolvedTargets($scopeKeys);

        $batch = DB::transaction(function () use ($find, $replace, $scopeKeys, $opts, $userId, $pattern, $targets) {
            $batch = ContentReplaceBatch::create([
                'user_id' => $userId,
                'find' => $find,
                'replace' => $replace,
                'case_sensitive' => (bool) ($opts['case_sensitive'] ?? true),
                'whole_word' => (bool) ($opts['whole_word'] ?? false),
                'scopes' => array_values(array_intersect($scopeKeys, array_keys(self::SCOPES))),
                'records_count' => 0,
                'occurrences_count' => 0,
            ]);

            $records = 0;
            $occurrences = 0;

            foreach ($targets as $t) {
                $hasTimestamps = $this->hasColumn($t['table'], 'updated_at');

                foreach ($this->rows($t, $find) as $row) {
                    $update = [];
                    $snapshots = [];

                    foreach ($t['columns'] as $col) {
                        $value = $row->{$col} ?? null;
                        if ($value === null || $value === '') {
                            continue;
                        }
                        $count = preg_match_all($pattern, (string) $value);
                        if (! $count) {
                            continue;
                        }
                        $new = preg_replace_callback($pattern, fn () => $replace, (string) $value);
                        if ($new === $value) {
                            continue; // find === replace, nothing to do
                        }

                        $update[$col] = $new;
                        $snapshots[] = [
                            'batch_id' => $batch->id,
                            'table_name' => $t['table'],
                            'column_name' => $col,
                            'record_id' => $row->id,
                            'old_value' => (string) $value,
                            'new_value' => $new,
                            'occurrences' => $count,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $occurrences += $count;
                    }

                    if ($update === []) {
                        continue;
                    }

                    if ($hasTimestamps) {
                        $update['updated_at'] = now();
                    }

                    DB::table($t['table'])->where('id', $row->id)->update($update);
                    DB::table('content_replace_items')->insert($snapshots);
                    $records++;
                }
            }

            $batch->update(['records_count' => $records, 'occurrences_count' => $occurrences]);

            return $batch;
        });

        $this->flushCaches();
        $this->audit('content_replace', $batch);

        return $batch;
    }

    /**
     * Revert a batch to the exact pre-replace values. A field whose current
     * value no longer matches what we wrote (edited since) is skipped, so undo
     * never destroys newer manual edits.
     *
     * @return array{restored:int, skipped:int}
     */
    public function revert(ContentReplaceBatch $batch): array
    {
        if ($batch->isReverted()) {
            return ['restored' => 0, 'skipped' => 0];
        }

        $result = DB::transaction(function () use ($batch) {
            $restored = 0;
            $skipped = 0;

            foreach ($batch->items()->get() as $item) {
                if (! $this->hasColumn($item->table_name, $item->column_name)) {
                    $skipped++;
                    continue;
                }

                $current = DB::table($item->table_name)->where('id', $item->record_id)->value($item->column_name);

                // Only restore if the field still holds exactly what we wrote.
                if ((string) $current !== (string) $item->new_value) {
                    $skipped++;
                    continue;
                }

                $update = [$item->column_name => $item->old_value];
                if ($this->hasColumn($item->table_name, 'updated_at')) {
                    $update['updated_at'] = now();
                }
                DB::table($item->table_name)->where('id', $item->record_id)->update($update);
                $restored++;
            }

            $batch->update(['reverted_at' => now()]);

            return ['restored' => $restored, 'skipped' => $skipped];
        });

        $this->flushCaches();
        $this->audit('content_replace_revert', $batch);

        return $result;
    }

    // ── Internals ─────────────────────────────────────────────────────

    protected function requireFind(string $find): string
    {
        if (trim($find) === '') {
            throw new InvalidArgumentException('The search text cannot be empty.');
        }

        return $find;
    }

    /** Resolve options to their defaults (case-sensitive ON, whole-word OFF). */
    protected function normalizeOpts(array $opts): array
    {
        return [
            'case_sensitive' => (bool) ($opts['case_sensitive'] ?? true),
            'whole_word' => (bool) ($opts['whole_word'] ?? false),
        ];
    }

    /** Build the match pattern for all four case/word combinations. */
    protected function pattern(string $find, array $opts): string
    {
        $opts = $this->normalizeOpts($opts);
        $quoted = preg_quote($find, '/');

        if ($opts['whole_word']) {
            // Unicode-aware word boundaries (letters, digits, underscore).
            $quoted = '(?<![\p{L}\p{N}_])'.$quoted.'(?![\p{L}\p{N}_])';
        }

        return '/'.$quoted.'/u'.($opts['case_sensitive'] ? '' : 'i');
    }

    /**
     * Resolve requested scope keys to concrete, schema-verified targets.
     * Unknown keys and missing tables/columns are dropped defensively.
     */
    protected function resolvedTargets(array $scopeKeys): array
    {
        $targets = [];

        foreach ($scopeKeys as $key) {
            $cfg = self::SCOPES[$key] ?? null;
            if (! $cfg || ! Schema::hasTable($cfg['table'])) {
                continue;
            }

            $cols = array_values(array_filter($cfg['columns'], fn ($c) => $this->hasColumn($cfg['table'], $c)));
            if ($cols === []) {
                continue;
            }

            $targets[] = [
                'key' => $key,
                'table' => $cfg['table'],
                'columns' => $cols,
                'type' => $cfg['type'],
                'where' => $cfg['where'] ?? [],
                'parent_table' => $cfg['parent_table'] ?? null,
                'parent_key' => $cfg['parent_key'] ?? null,
            ];
        }

        return $targets;
    }

    /**
     * Rows in a target whose ANY whitelisted column contains the find string
     * (LIKE prefilter; the regex refines exact counts later). Yields id + the
     * target columns + a label column when present.
     *
     * @return iterable<\stdClass>
     */
    protected function rows(array $t, string $find): iterable
    {
        $select = array_merge(['id'], $t['columns']);
        if ($labelCol = $this->labelColumn($t['table'])) {
            $select[] = $labelCol;
        }
        foreach ((array) $t['where'] as $col => $_) {
            if (! in_array($col, $select, true)) {
                $select[] = $col;
            }
        }
        if ($t['parent_key'] && ! in_array($t['parent_key'], $select, true)) {
            $select[] = $t['parent_key'];
        }

        $like = '%'.$this->escapeLike($find).'%';

        $rows = collect();
        DB::table($t['table'])
            ->select(array_unique($select))
            ->where($t['where'] ?: [])
            ->where(function ($q) use ($t, $like) {
                foreach ($t['columns'] as $col) {
                    $q->orWhere($col, 'like', $like);
                }
            })
            ->orderBy('id')
            ->chunk(200, function ($chunk) use (&$rows) {
                foreach ($chunk as $r) {
                    $rows->push($r);
                }
            });

        // Resolve parent names for SEO rows in one batched query.
        $parents = $this->resolveParents($t, $rows);
        foreach ($rows as $r) {
            if ($parents !== null) {
                $r->_parent_label = $parents[$r->{$t['parent_key']}] ?? null;
            }
            yield $r;
        }
    }

    /** For SEO scopes: map parent id => parent name/title for readable labels. */
    protected function resolveParents(array $t, $rows): ?array
    {
        if (! $t['parent_table'] || ! $t['parent_key'] || $rows->isEmpty()) {
            return null;
        }
        $labelCol = $this->labelColumn($t['parent_table']);
        if (! $labelCol) {
            return [];
        }
        $ids = $rows->pluck($t['parent_key'])->filter()->unique()->all();

        return DB::table($t['parent_table'])->whereIn('id', $ids)->pluck($labelCol, 'id')->all();
    }

    /** A human label for a matched row (parent name for SEO, else name/title/id). */
    protected function labelsFor(array $t, array $row): array
    {
        $r = (object) $row;
        if (! empty($r->_parent_label)) {
            return ['label' => $r->_parent_label.' (#'.($r->{$t['parent_key']} ?? $r->id).')'];
        }
        $labelCol = $this->labelColumn($t['table']);
        $name = $labelCol ? ($row[$labelCol] ?? null) : null;

        return ['label' => $name ? $name.' (#'.$row['id'].')' : '#'.$row['id']];
    }

    /** First existing "human name" column for a table, or null. */
    protected function labelColumn(string $table): ?string
    {
        foreach (['name', 'title', 'question', 'code', 'sku'] as $c) {
            if ($this->hasColumn($table, $c)) {
                return $c;
            }
        }

        return null;
    }

    protected function humanColumn(string $col): string
    {
        return ucfirst(str_replace('_', ' ', $col));
    }

    /** Short, tag-free snippet around the first match, marked with «». */
    protected function snippet(string $value, string $pattern): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? $value);

        // Re-locate the match in the stripped text (best-effort; falls back to head).
        if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
            $len = strlen($m[0][0]);
            $start = max(0, $pos - 40);
            $head = $start > 0 ? '…' : '';
            $tail = ($pos + $len + 40) < strlen($text) ? '…' : '';
            $before = substr($text, $start, $pos - $start);
            $hit = substr($text, $pos, $len);
            $after = substr($text, $pos + $len, 40);

            return $head.$before.'«'.$hit.'»'.$after.$tail;
        }

        return mb_substr($text, 0, 90).(mb_strlen($text) > 90 ? '…' : '');
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    protected function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $k = $table.'.'.$column;

        return $cache[$k] ??= Schema::hasColumn($table, $column);
    }

    /** Invalidate guest/edge caches so changed content shows. Never throws. */
    protected function flushCaches(): void
    {
        try {
            PageCache::flush();
        } catch (Throwable $e) {
            report($e);
        }

        try {
            app(\App\Services\Performance\LiteSpeedPurger::class)->purgeAll();
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function audit(string $action, ContentReplaceBatch $batch): void
    {
        try {
            \App\Models\AuditLog::create([
                'user_id' => $batch->user_id,
                'action' => $action,
                'subject' => 'content_replace:'.$batch->id,
                'new_values' => [
                    'find' => $batch->find,
                    'replace' => $batch->replace,
                    'scopes' => $batch->scopes,
                    'records' => $batch->records_count,
                    'occurrences' => $batch->occurrences_count,
                ],
                'ip_address' => request()->ip(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
