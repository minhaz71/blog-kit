<?php

namespace App\Services\Research\Drivers;

use App\Services\Research\Contracts\ResearchDriver;
use Illuminate\Support\Facades\Http;

/**
 * FREE driver: Google Autocomplete (suggestqueries). Returns the real phrases
 * Google suggests for a seed and for question prefixes — genuine demand signal,
 * no API key, but NO search-volume numbers and no SERP. Always available; used
 * as the fallback under DataForSEO and to enrich its results.
 */
class GoogleAutocompleteDriver implements ResearchDriver
{
    /** Question prefixes that surface People-Also-Ask-style phrases. */
    protected const PREFIXES = ['how to', 'how', 'what', 'why', 'when', 'where', 'which', 'is', 'are', 'can', 'best'];

    public function name(): string
    {
        return 'google';
    }

    public function available(): bool
    {
        return true; // free, no credentials
    }

    public function discover(array $seeds, array $opts = []): array
    {
        $lang = (string) ($opts['language'] ?? 'en');
        $seeds = array_slice(array_values(array_filter(array_map('trim', $seeds))), 0, 100);
        $out = [];

        foreach ($seeds as $i => $seed) {
            if ($seed === '') {
                continue;
            }

            // Plain suggestions for the seed.
            foreach ($this->suggest($seed, $lang) as $s) {
                $out[] = $this->term($s, $this->looksLikeQuestion($s) ? 'question' : 'related');
            }

            // Question-prefix suggestions (real People-Also-Ask-style phrases).
            // Bounded to the first 20 seeds so a 100-keyword run stays fast.
            if ($i < 20) {
                foreach (self::PREFIXES as $p) {
                    foreach ($this->suggest($p.' '.$seed, $lang) as $s) {
                        if ($this->looksLikeQuestion($s)) {
                            $out[] = $this->term($s, 'question');
                        }
                    }
                }
            }
        }

        return $out;
    }

    public function serp(string $keyword, array $opts = []): array
    {
        return []; // no reliable free SERP
    }

    /** Hit Google's public suggest endpoint; returns the suggestion strings. */
    protected function suggest(string $query, string $lang): array
    {
        try {
            $body = Http::timeout(8)->retry(1, 200)
                ->get('https://suggestqueries.google.com/complete/search', [
                    'client' => 'firefox',
                    'hl' => $lang,
                    'q' => $query,
                ])->throw()->body();

            $json = json_decode($body, true);

            return array_values(array_filter((array) ($json[1] ?? []), 'is_string'));
        } catch (\Throwable) {
            return [];
        }
    }

    protected function looksLikeQuestion(string $s): bool
    {
        return (bool) preg_match('/^(how|what|why|when|where|which|who|is|are|can|do|does|should|will)\b/i', trim($s))
            || str_contains($s, '?');
    }

    protected function term(string $keyword, string $source): array
    {
        return [
            'keyword' => trim($keyword),
            'volume' => null,
            'difficulty' => null,
            'cpc' => null,
            'intent' => null,
            'source' => $source,
        ];
    }
}
