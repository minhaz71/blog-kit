<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Product / Post / Category referenced by phraseAppearsInContent().

/**
 * An admin-defined internal-link destination that is NOT an Eloquent
 * product/post/category — e.g. the homepage or a landing page. Slots into
 * the polymorphic link tables as target_type=CustomLinkTarget so the whole
 * link agent (dictionary, suggestion engine, applier, unlink) treats it
 * like any other target.
 *
 * Carries several anchor_phrases for natural variety, and a site-wide
 * max_links cap so a base page never accrues spammy over-linking.
 */
class CustomLinkTarget extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'anchor_phrases' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** Polymorphic target contract: the applier/report call ->url() and ->name. */
    public function url(): string
    {
        return $this->url;
    }

    public function getNameAttribute(): string
    {
        return $this->attributes['label'] ?? $this->url;
    }

    /** Memoized per instance so a table row's several column closures share one lookup. */
    protected ?array $unmatchedCache = null;

    /**
     * Anchor phrases that do NOT appear verbatim in any content the link
     * agent actually scans — so they can never produce a suggestion. Checks
     * the SAME fields the engine reads: product description/short description,
     * category content block, and post content (published/active only).
     *
     * @return array<int, string>
     */
    public function unmatchedAnchorPhrases(): array
    {
        return $this->unmatchedCache ??= array_values(array_filter(
            array_map('trim', (array) $this->anchor_phrases),
            fn (string $phrase) => $phrase !== '' && ! self::phraseAppearsInContent($phrase),
        ));
    }

    /** True when the phrase occurs in any live product/post/category body the agent scans. */
    public static function phraseAppearsInContent(string $phrase): bool
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim($phrase)).'%';

        $inProducts = Product::query()->where('status', 'published')
            ->where(fn ($q) => $q->where('description', 'like', $like)->orWhere('short_description', 'like', $like))
            ->exists();

        if ($inProducts) {
            return true;
        }

        $inPosts = Post::query()->published()->where('content', 'like', $like)->exists();

        if ($inPosts) {
            return true;
        }

        return Category::query()->where('is_active', true)->where('content_block', 'like', $like)->exists();
    }
}
