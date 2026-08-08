<?php

namespace App\Services\Research\Contracts;

/**
 * A source of real keyword/topic data. Drivers are tried in preference order
 * (paid → free → LLM) with graceful fallback, so research always returns
 * something useful even with no API key.
 */
interface ResearchDriver
{
    public function name(): string;

    /** True when this driver is configured/usable right now. */
    public function available(): bool;

    /**
     * Expand seed keywords into a wider universe of related terms + questions,
     * with whatever metrics this source provides (volume/difficulty/cpc/intent
     * are null when unknown).
     *
     * @param  array<int, string>  $seeds
     * @param  array<string, mixed>  $opts  location_code, language, target
     * @return array<int, array{keyword:string, volume:?int, difficulty:?int, cpc:?float, intent:?string, source:string}>
     */
    public function discover(array $seeds, array $opts = []): array;

    /**
     * Top organic result URLs for a keyword — the signal for SERP-overlap
     * clustering. Returns [] when the source can't provide SERP data.
     *
     * @return array<int, string>
     */
    public function serp(string $keyword, array $opts = []): array;
}
