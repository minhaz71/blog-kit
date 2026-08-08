<?php

namespace App\Services\Research;

use App\Services\Research\Contracts\ResearchDriver;
use App\Services\Research\Drivers\DataForSeoDriver;
use App\Services\Research\Drivers\GoogleAutocompleteDriver;
use App\Services\Research\Drivers\LlmDriver;

/**
 * The keyword-research facade: resolves the configured driver chain (paid →
 * free → LLM), discovers a de-duplicated term universe from the seed keywords,
 * and exposes SERP lookup for overlap clustering. Real numbers when DataForSEO
 * is set; still useful (real questions, no volume) on the free path.
 */
class KeywordResearch
{
    /**
     * Discover the term universe for the seeds. Seeds themselves are always
     * included (source 'seed'); discovered terms are merged and de-duplicated.
     *
     * @param  array<int, string>  $seeds
     * @return array<int, array{keyword:string, volume:?int, difficulty:?int, cpc:?float, intent:?string, source:string}>
     */
    public function discover(array $seeds, array $opts = []): array
    {
        $seeds = array_values(array_filter(array_map('trim', $seeds)));
        $target = (int) ($opts['target'] ?? 400);

        $collected = array_map(fn ($s) => [
            'keyword' => $s, 'volume' => null, 'difficulty' => null,
            'cpc' => null, 'intent' => null, 'source' => 'seed',
        ], $seeds);

        foreach ($this->drivers() as $driver) {
            try {
                $collected = array_merge($collected, $driver->discover($seeds, $opts));
            } catch (\Throwable) {
                // A driver failing must never abort research — try the next.
            }

            // Stop once the paid/primary source has given us plenty.
            if (count($collected) >= $target + count($seeds)) {
                break;
            }
        }

        return $this->dedupe($collected);
    }

    /** Top result hosts for a keyword, from the first driver that can answer. */
    public function serp(string $keyword, array $opts = []): array
    {
        foreach ($this->drivers() as $driver) {
            $urls = $driver->serp($keyword, $opts);
            if ($urls !== []) {
                return $urls;
            }
        }

        return [];
    }

    /** True when a real (non-LLM) data source is configured. */
    public function hasRealData(): bool
    {
        foreach ($this->drivers() as $driver) {
            if (in_array($driver->name(), ['dataforseo', 'google'], true)) {
                return true;
            }
        }

        return false;
    }

    /** True when the paid SERP source is available (enables SERP-overlap clustering). */
    public function hasSerp(): bool
    {
        return app(DataForSeoDriver::class)->available();
    }

    /**
     * The active driver chain in preference order, availability-filtered.
     *
     * @return array<int, ResearchDriver>
     */
    public function drivers(): array
    {
        $dfs = app(DataForSeoDriver::class);
        $google = app(GoogleAutocompleteDriver::class);
        $llm = app(LlmDriver::class);

        $ordered = match ((string) setting('research.provider', 'auto')) {
            'dataforseo' => [$dfs, $google, $llm],
            'google' => [$google, $llm],
            'llm' => [$llm],
            default => [$dfs, $google, $llm], // auto
        };

        return array_values(array_filter($ordered, fn (ResearchDriver $d) => $d->available()));
    }

    /**
     * De-duplicate by normalized token key, keeping the richest row (prefer one
     * with a volume number, then the earliest/most-authoritative source).
     */
    protected function dedupe(array $terms): array
    {
        $best = [];

        foreach ($terms as $t) {
            $key = \App\Models\KeywordResearchTerm::normalize((string) $t['keyword']);
            if ($key === '') {
                continue;
            }

            $existing = $best[$key] ?? null;
            if ($existing === null) {
                $best[$key] = $t;

                continue;
            }

            // Merge: keep any known metric from either row; prefer a seed source.
            $best[$key] = [
                'keyword' => $existing['keyword'],
                'volume' => $existing['volume'] ?? $t['volume'],
                'difficulty' => $existing['difficulty'] ?? $t['difficulty'],
                'cpc' => $existing['cpc'] ?? $t['cpc'],
                'intent' => $existing['intent'] ?? $t['intent'],
                'source' => $existing['source'] === 'seed' || $t['source'] === 'seed' ? 'seed' : $existing['source'],
            ];
        }

        return array_values($best);
    }
}
