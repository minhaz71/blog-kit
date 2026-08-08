<?php

namespace App\Services\Research\Drivers;

use App\Services\Ai\LlmClient;
use App\Services\Research\Contracts\ResearchDriver;

/**
 * Fallback driver: expand seeds with the LLM when no real data source is set
 * (or to top up a thin result). No volume/SERP — the model's best guess at
 * related searches and questions. Available only when an AI provider key exists.
 */
class LlmDriver implements ResearchDriver
{
    public function name(): string
    {
        return 'llm';
    }

    public function available(): bool
    {
        return $this->provider() !== null;
    }

    public function discover(array $seeds, array $opts = []): array
    {
        $provider = $this->provider();
        if ($provider === null || $seeds === []) {
            return [];
        }

        $system = <<<'SYS'
You are a keyword research assistant. Expand the given seed keywords into the real search phrases people type around this topic — a mix of informational questions, comparisons, and buying/decision phrases. Use natural, specific long-tail phrasing.
Return ONLY JSON: {"terms":[{"keyword":"<phrase>","intent":"informational"|"commercial"|"transactional"|"navigational","question":true|false}]}
Rules: 40-120 terms, all realistic phrases a person would actually search, distinct from each other, no volume numbers, no made-up brand names.
SYS;

        $user = "SEED KEYWORDS:\n- ".implode("\n- ", array_slice($seeds, 0, 100))
            .($opts['country'] ?? null ? "\n\nTARGET MARKET: ".$opts['country'] : '')
            .'\n\nExpand them now.';

        try {
            $parsed = LlmClient::parseJson(
                LlmClient::for($provider)->withContext('plan')->complete($system, $user, maxTokens: 4000)
            );
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ((array) ($parsed['terms'] ?? []) as $t) {
            $kw = trim((string) ($t['keyword'] ?? ''));
            if ($kw === '') {
                continue;
            }
            $out[] = [
                'keyword' => $kw,
                'volume' => null,
                'difficulty' => null,
                'cpc' => null,
                'intent' => $t['intent'] ?? null,
                'source' => ! empty($t['question']) ? 'question' : 'related',
            ];
        }

        return $out;
    }

    public function serp(string $keyword, array $opts = []): array
    {
        return [];
    }

    protected function provider(): ?string
    {
        foreach (['anthropic', 'openai', 'gemini'] as $p) {
            if (trim((string) setting("ai.{$p}_api_key")) !== '') {
                return $p;
            }
        }

        return null;
    }
}
