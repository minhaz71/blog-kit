<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    protected $guarded = [];

    public function batch()
    {
        return $this->belongsTo(AiImportBatch::class, 'batch_id');
    }

    /**
     * $ per million tokens: [input, output, cached-input].
     * Matched by longest key found on a token boundary in the model name.
     */
    public const PRICES = [
        'claude-fable-5' => [10.00, 50.00, 1.00],
        // Introductory pricing through 2026-08-31; priceFor() switches to
        // [3.00, 15.00, 0.30] automatically from 2026-09-01.
        'claude-sonnet-5' => [2.00, 10.00, 0.20],
        'claude-opus' => [15.00, 75.00, 1.50],
        'claude-sonnet' => [3.00, 15.00, 0.30],
        'claude-haiku' => [1.00, 5.00, 0.10],
        'gpt-4o-mini' => [0.15, 0.60, 0.075],
        'gpt-4o' => [2.50, 10.00, 1.25],
        'gpt-4.1-mini' => [0.40, 1.60, 0.10],
        'gpt-4.1' => [2.00, 8.00, 0.50],
        'o4-mini' => [1.10, 4.40, 0.275],
        'o3-mini' => [1.10, 4.40, 0.55],
        'o3' => [2.00, 8.00, 0.50],
        'gemini-2.5-pro' => [1.25, 10.00, 0.31],
        'gemini-2.5-flash' => [0.30, 2.50, 0.075],
        'gemini-2.0-flash' => [0.10, 0.40, 0.025],
        'gemini-1.5-pro' => [1.25, 5.00, 0.3125],
        'gemini' => [0.10, 0.40, 0.025],
    ];

    /** Anthropic bills prompt-cache WRITES at 1.25× the input rate. */
    public const CACHE_WRITE_MULTIPLIER = 1.25;

    public static function priceFor(string $model): array
    {
        $best = null;
        $bestLen = 0;

        foreach (self::PRICES as $key => $price) {
            // Boundary-aware: 'o3' matches 'o3' / 'o3-2025' but never the
            // middle of another token; longest key wins ('o3-mini' > 'o3').
            $onBoundary = (bool) preg_match(
                '/(?<![a-z0-9])'.preg_quote($key, '/').'(?![a-z0-9])/i',
                $model,
            );

            if ($onBoundary && strlen($key) > $bestLen) {
                $best = $price;
                $bestLen = strlen($key);
                $bestKey = $key;
            }
        }

        // Sonnet 5 introductory pricing ends 2026-08-31.
        if (($bestKey ?? null) === 'claude-sonnet-5' && now()->gte('2026-09-01')) {
            return [3.00, 15.00, 0.30];
        }

        return $best ?? [0.0, 0.0, 0.0];
    }

    /** False for admin-added models with no pricing row — flagged on the dashboard. */
    public static function isPriced(string $model): bool
    {
        return self::priceFor($model) !== [0.0, 0.0, 0.0];
    }

    public static function record(
        string $provider,
        string $model,
        int $input,
        int $output,
        int $cached = 0,
        string $purpose = 'write',
        ?int $batchId = null,
        ?int $itemId = null,
        int $cacheWrite = 0,
    ): self {
        [$inPrice, $outPrice, $cachePrice] = self::priceFor($model);

        // Cache reads bill at the cache rate, cache writes at 1.25× input,
        // the remainder at the plain input rate.
        $plainInput = max(0, $input - $cached - $cacheWrite);
        $cost = (
            $plainInput * $inPrice
            + $cached * $cachePrice
            + $cacheWrite * $inPrice * self::CACHE_WRITE_MULTIPLIER
            + $output * $outPrice
        ) / 1_000_000;

        return self::create([
            'provider' => $provider,
            'model' => $model,
            'purpose' => $purpose,
            'input_tokens' => $input,
            'output_tokens' => $output,
            'cached_tokens' => $cached,
            'cache_write_tokens' => $cacheWrite,
            'cost' => round($cost, 6),
            'batch_id' => $batchId,
            'item_id' => $itemId,
        ]);
    }

    /**
     * Log a FLAT, non-token cost — e.g. image generation, which bills per image
     * (not per token). Recorded with zero tokens and purpose 'image' so it shows
     * up in the same AI cost reports and batch spend as the text usage.
     */
    public static function recordFlat(
        string $provider,
        string $model,
        float $cost,
        string $purpose = 'image',
        ?int $batchId = null,
        ?int $itemId = null,
    ): self {
        return self::create([
            'provider' => $provider,
            'model' => $model,
            'purpose' => $purpose,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cached_tokens' => 0,
            'cache_write_tokens' => 0,
            'cost' => round($cost, 6),
            'batch_id' => $batchId,
            'item_id' => $itemId,
        ]);
    }
}
