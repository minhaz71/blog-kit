<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiImportBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'allowed_tags' => 'array',
            'link_catalog' => 'array',
            'network_site_ids' => 'array',
            'generate_images' => 'boolean',
        ];
    }

    public const OUTPUT_FORMATS = [
        'html_css' => 'HTML + scoped CSS (design included)',
        'html_plain' => 'Plain semantic HTML tags only (no classes, no CSS)',
        'html_classes' => 'HTML using my own CSS classes',
    ];

    public const TAG_OPTIONS = [
        'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'p' => 'p',
        'ul' => 'ul / li', 'ol' => 'ol / li', 'table' => 'table',
        'blockquote' => 'blockquote', 'strong' => 'strong / em', 'a' => 'a (links)',
        'img' => 'img', 'div' => 'div',
    ];

    /**
     * Default store brief for AI writing — the single source of store
     * facts. SELLING UNIT is stated as a hard fact because the models
     * otherwise fall back to general TEREA knowledge (packs of 20) and
     * invent single-pack purchase copy.
     */
    public const DEFAULT_STORE_BRIEF = 'Terea Hub is a UAE store for genuine IQOS TEREA sticks and ILUMA devices. '
        .'SELLING UNIT (hard fact): TEREA is sold as FULL CARTONS ONLY, 1 carton = 10 packs = 200 sticks; '
        .'we never sell single packs — never describe buying a single pack. '
        .'1-hour delivery in Dubai, Sharjah and Ajman; 12-hour delivery UAE-wide; cash or card on delivery. '
        .'Audience: adult IQOS users in the UAE. Tone: expert, honest, practical. '
        .'Never make health claims; say "smoke-free experience" only. Adults 18+ only.';

    /** "Delay between articles" choices — 1 hour to 1 year, in minutes. */
    public const PUBLISH_INTERVALS = [
        60 => 'Every hour',
        120 => 'Every 2 hours',
        360 => 'Every 6 hours',
        720 => 'Every 12 hours',
        1440 => 'Daily (1 article per day)',
        2880 => 'Every 2 days',
        4320 => 'Every 3 days',
        10080 => 'Weekly',
        20160 => 'Every 2 weeks',
        43200 => 'Monthly',
        129600 => 'Every 3 months',
        259200 => 'Every 6 months',
        525600 => 'Yearly',
    ];

    public const PROVIDERS = [
        'anthropic' => 'Claude (Anthropic)',
        'openai' => 'GPT (OpenAI)',
        'gemini' => 'Gemini (Google)',
    ];

    /**
     * Real, currently-shipping model IDs per provider, for the dependent
     * "Model" dropdown — kept in sync with AiUsageLog::PRICES so every
     * selectable model has a known cost.
     */
    public const MODELS = [
        'anthropic' => [
            'claude-sonnet-5' => 'Claude Sonnet 5 — recommended (fast, balanced cost)',
            'claude-fable-5' => 'Claude Fable 5 — frontier, most capable',
            'claude-opus-4-8' => 'Claude Opus 4.8 — high capability',
            'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
            'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 — fastest, cheapest',
        ],
        'openai' => [
            'gpt-4o-mini' => 'GPT-4o mini — recommended reviewer (fast, cheapest)',
            'gpt-4.1-mini' => 'GPT-4.1 mini — cheap, stronger than 4o-mini',
            'gpt-4o' => 'GPT-4o',
            'gpt-4.1' => 'GPT-4.1',
            'o4-mini' => 'o4-mini — cheap reasoning model',
            'o3' => 'o3 — reasoning model',
        ],
        'gemini' => [
            'gemini-2.0-flash' => 'Gemini 2.0 Flash — recommended (fast, cheapest)',
            'gemini-2.5-flash' => 'Gemini 2.5 Flash',
            'gemini-2.5-pro' => 'Gemini 2.5 Pro — higher capability',
            'gemini-1.5-pro' => 'Gemini 1.5 Pro',
        ],
    ];

    /**
     * Dropdown options: the curated list + models the admin added in
     * Settings → AI settings ("Extra models", one per line as
     * "model-id | Optional label"). No network calls — when a provider
     * ships a new model, paste its id there and it appears here.
     */
    public static function modelOptions(string $provider): array
    {
        $extras = [];

        foreach (preg_split('/\r?\n/', (string) setting("ai.{$provider}_extra_models")) as $line) {
            [$id, $label] = array_pad(array_map('trim', explode('|', $line, 2)), 2, null);

            if ($id !== null && $id !== '') {
                $extras[$id] = $label ?: $id;
            }
        }

        return (self::MODELS[$provider] ?? []) + $extras;
    }

    /** Writer and reviewer resolve to the same provider+model → one-call review mode. */
    public function usesCombinedReview(): bool
    {
        $writerModel = $this->model ?: \App\Services\Ai\LlmClient::defaultModel($this->provider);
        $reviewerModel = $this->reviewer_model ?: \App\Services\Ai\LlmClient::defaultModel($this->reviewer_provider ?: 'openai');

        return $this->provider === ($this->reviewer_provider ?: 'openai') && $writerModel === $reviewerModel;
    }

    public function items()
    {
        return $this->hasMany(AiImportItem::class, 'batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function usageLogs()
    {
        return $this->hasMany(AiUsageLog::class, 'batch_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(AiActivityLog::class, 'batch_id');
    }

    /**
     * A background research/writer run that claimed "processing" but hasn't
     * logged anything for a while — the detached OS process was almost
     * certainly killed (host time/memory limit) before it could record a
     * failure. Surfaced so the admin can Retry instead of staring at a
     * table that never fills.
     */
    public function isStalled(int $minutes = 10): bool
    {
        return $this->status === 'processing'
            && $this->updated_at !== null
            && $this->updated_at->lt(now()->subMinutes($minutes));
    }

    public function progressPercent(): int
    {
        return $this->total_items > 0
            ? (int) round(($this->done_items + $this->failed_items) / $this->total_items * 100)
            : 0;
    }
}
