<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogTopicIdea extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'secondary_keywords' => 'array',
            'outline' => 'array',
            'link_targets' => 'array',
            'compared_product_ids' => 'array',
        ];
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Normalized token-set fingerprint — same technique as the link agent:
     * lowercase, strip punctuation, drop trivial words, sort tokens. Titles
     * that reshuffle the same words collide instead of duplicating.
     */
    public static function fingerprint(string $title): string
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($title), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_diff($tokens, ['a', 'an', 'the', 'of', 'in', 'on', 'for', 'to', 'and', 'or', 'is', 'are', 'your', 'you', 'with', 'vs']));
        sort($tokens);

        return md5(implode(' ', $tokens));
    }

    /**
     * Everything a new blog title must not compete with: existing article
     * titles, parked ideas, and — because a blog post outranking your own
     * money pages is self-sabotage — published PRODUCT names and active
     * CATEGORY names.
     *
     * @return array<int, string>
     */
    public static function conflictCorpus(bool $includePosts = true): array
    {
        $corpus = self::query()
            ->whereIn('status', ['waiting', 'queued', 'written'])
            ->pluck('title')
            ->concat(\App\Models\Product::query()->where('status', 'published')->pluck('name'))
            ->concat(\App\Models\Category::query()->where('is_active', true)->pluck('name'));

        if ($includePosts) {
            $corpus = $corpus->concat(Post::query()->pluck('title'));
        }

        return $corpus->map(fn ($t) => trim((string) $t))->filter()->values()->all();
    }

    /** The first corpus entry this title is too similar to, or null when clear. */
    public static function rankingConflict(string $title, array $corpus, float $limit = 0.6): ?string
    {
        foreach ($corpus as $other) {
            if (self::similarity($title, $other) >= $limit) {
                return $other;
            }
        }

        return null;
    }

    /**
     * Jaccard token similarity between two titles (0..1) — the deterministic
     * half of the "would this compete with an existing article" canonical
     * guard.
     */
    public static function similarity(string $a, string $b): float
    {
        $tokenize = function (string $t): array {
            $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($t), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $tokens = array_diff($tokens, [
                'a', 'an', 'the', 'of', 'in', 'on', 'for', 'to', 'and', 'or', 'is', 'are',
                'your', 'you', 'with', 'how', 'what', 'why', 'do', 'it', 'its', 'this', 'that',
            ]);

            // Light stemming so "cleaning"/"clean", "guides"/"guide" collide —
            // reworded titles about the same topic must read as similar.
            return array_unique(array_map(function (string $word): string {
                foreach (['ing', 'ed', 'es'] as $suffix) {
                    if (str_ends_with($word, $suffix) && mb_strlen($word) > mb_strlen($suffix) + 3) {
                        return mb_substr($word, 0, -mb_strlen($suffix));
                    }
                }

                return str_ends_with($word, 's') && mb_strlen($word) > 4 ? mb_substr($word, 0, -1) : $word;
            }, $tokens));
        };

        $ta = $tokenize($a);
        $tb = $tokenize($b);

        if ($ta === [] || $tb === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($ta, $tb));
        $union = count(array_unique(array_merge($ta, $tb)));

        return $union > 0 ? $intersection / $union : 0.0;
    }
}
