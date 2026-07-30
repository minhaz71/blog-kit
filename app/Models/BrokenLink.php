<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A dead internal link: `source` (a page) links to `url`, which belonged to
 * a now-deleted product/post (`target`). Open reports are unresolved; they
 * resolve automatically when the target is restored or the source page stops
 * linking to the dead URL, and can be dismissed manually.
 */
class BrokenLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function target()
    {
        return $this->morphTo();
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    /**
     * Remove the dead <a> tag from the source page's content, keeping the
     * anchor text as plain text, and resolve this report. Matching is by URL
     * path so relative ("/product/x") and absolute forms both unwrap.
     * Returns how many anchors were removed (0 when the source is gone or
     * the link was already edited away — the report still resolves).
     */
    public function unlink(): int
    {
        $source = $this->source;
        $deadPath = (string) parse_url((string) $this->url, PHP_URL_PATH);

        if (! $source || $deadPath === '') {
            $this->update(['resolved_at' => now()]);

            return 0;
        }

        $columns = match ($this->source_type) {
            Product::class => ['description', 'short_description'],
            Post::class => ['content'],
            default => [],
        };

        $removed = 0;
        $dirty = [];

        foreach ($columns as $column) {
            $original = (string) $source->{$column};

            if ($original === '') {
                continue;
            }

            $html = preg_replace_callback(
                '~<a\s[^>]*?href=(["\'])(.*?)\1[^>]*>(.*?)</a>~is',
                function (array $match) use ($deadPath, &$removed): string {
                    $path = (string) parse_url(html_entity_decode($match[2]), PHP_URL_PATH);

                    if (rtrim($path, '/') === rtrim($deadPath, '/')) {
                        $removed++;

                        return $match[3]; // keep the visible text, drop the tag
                    }

                    return $match[0];
                },
                $original,
            );

            if ($html !== $original) {
                $dirty[$column] = $html;
            }
        }

        if ($dirty !== []) {
            // Normal update: observers re-scan the source, purge its cache,
            // and posts keep their revision trail.
            $source->update($dirty);
        }

        $this->update(['resolved_at' => now()]);

        return $removed;
    }
}
