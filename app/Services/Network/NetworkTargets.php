<?php

namespace App\Services\Network;

use App\Models\ConnectedSite;

/**
 * Resolves a "which sites" selection into a concrete publish plan.
 *
 * Unlike the older {@see NetworkPublisher::resolveTargets()} (which only ever
 * returned spoke IDs and assumed the local site was always published to), this
 * treats THIS install as a first-class, selectable target — exactly like the
 * checkbox list in the AI Blog Writer. The returned shape says both whether to
 * publish here AND which connected spokes to fan out to:
 *
 *   ['local' => bool, 'sites' => int[]]
 *
 * Accepted input (array from the checkbox list, or a CSV string from a row):
 *  - empty / null                → publish here only (backward compatible)
 *  - contains "all"              → here + every active spoke
 *  - a local token (self/this/…) → publish here
 *  - numeric IDs                 → those active spokes
 *  - a "local only" token (none/off/no) → here only, no spokes
 *
 * Safety: a selection that resolves to nowhere (no local, no valid spokes)
 * falls back to publishing here — an article is never silently dropped.
 */
class NetworkTargets
{
    /** Tokens that mean "include this (local) site". */
    public const LOCAL_TOKENS = ['local', 'self', 'this', 'here', 'current', 'own'];

    /** Tokens that mean "keep it on this site only" (local, no spokes). */
    public const LOCAL_ONLY_TOKENS = ['none', 'off', 'no'];

    /** The sentinel stored in the checkbox list / CSV for the local site. */
    public const LOCAL = 'local';

    /**
     * @return array{local: bool, sites: array<int>}
     */
    public static function resolve(mixed $value): array
    {
        $tokens = self::tokenize($value);

        // Nothing selected → publish here only (matches the historical
        // behavior where an empty target meant "just this site").
        if ($tokens === []) {
            return ['local' => true, 'sites' => []];
        }

        $lower = array_map('strtolower', $tokens);

        $local = (bool) array_intersect($lower, self::LOCAL_TOKENS)
            || (bool) array_intersect($lower, self::LOCAL_ONLY_TOKENS);

        // "all" → here + every active spoke.
        if (in_array('all', $lower, true)) {
            return ['local' => true, 'sites' => self::activeSiteIds()];
        }

        $ids = array_values(array_unique(array_map(
            'intval',
            array_filter($tokens, fn ($t) => (int) $t > 0),
        )));

        $sites = $ids === []
            ? []
            : ConnectedSite::query()->active()->whereIn('id', $ids)->pluck('id')->map('intval')->all();

        // Never resolve to nowhere: if the user picked spokes only but none
        // are valid/active, keep the article here rather than losing it.
        if (! $local && $sites === []) {
            $local = true;
        }

        return ['local' => $local, 'sites' => $sites];
    }

    /**
     * A single grouping key for "which site this article was written for", used
     * for per-site cost attribution: the `local` sentinel when it's this site
     * only, the spoke ID when exactly one spoke, or 'shared' when it targets
     * more than one site (or all).
     */
    public const SHARED = 'shared';

    public static function siteKey(mixed $value): string
    {
        $r = self::resolve($value);

        if ($r['local'] && $r['sites'] === []) {
            return self::LOCAL;
        }

        if (! $r['local'] && count($r['sites']) === 1) {
            return (string) $r['sites'][0];
        }

        return self::SHARED;
    }

    /** All active connected-site IDs. */
    public static function activeSiteIds(): array
    {
        return ConnectedSite::query()->active()->pluck('id')->map('intval')->all();
    }

    /**
     * Normalize any accepted input into a flat list of string tokens.
     *
     * @return array<string>
     */
    protected static function tokenize(mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        $parts = is_array($value)
            ? $value
            : preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(
            array_map(fn ($t) => trim((string) $t), (array) $parts),
            fn ($t) => $t !== '',
        ));
    }
}
