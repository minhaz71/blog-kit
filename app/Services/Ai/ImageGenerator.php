<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Single-request text-to-image generation for blog thumbnails. ONE call per
 * image, no revision/refinement loop — the cheapest reliable path. Reuses the
 * same provider API keys as the text writer (ai.{provider}_api_key).
 *
 * Recommended model: OpenAI "gpt-image-1" (strong prompt adherence, returns
 * base64 directly). Alternative: Google "imagen-3.0-generate-002".
 */
class ImageGenerator
{
    /** provider => default image model. */
    public const DEFAULT_MODELS = [
        'openai' => 'gpt-image-1',
        'gemini' => 'imagen-3.0-generate-002',
    ];

    public const PROVIDER_LABELS = [
        'openai' => 'OpenAI — gpt-image-1 (recommended)',
        'gemini' => 'Google — Imagen 3',
    ];

    public static function provider(): string
    {
        $p = (string) setting('ai.image_provider', 'openai');

        return isset(self::DEFAULT_MODELS[$p]) ? $p : 'openai';
    }

    public static function model(?string $provider = null): string
    {
        $provider ??= self::provider();
        $model = trim((string) setting('ai.image_model', ''));

        return $model !== '' ? $model : self::DEFAULT_MODELS[$provider];
    }

    /** True when the configured image provider has an API key set. */
    public static function isConfigured(): bool
    {
        return trim((string) setting('ai.'.self::provider().'_api_key', '')) !== '';
    }

    /**
     * Generate exactly one image. Returns ['bytes' => binary, 'mime' => ...,
     * 'ext' => ...]. Throws on any failure (caller decides if it's fatal).
     *
     * @param  array{provider?:string,model?:string,size?:string,quality?:string}  $opts
     */
    public function generate(string $prompt, array $opts = []): array
    {
        $provider = $opts['provider'] ?? self::provider();
        $model = $opts['model'] ?? self::model($provider);
        $size = $opts['size'] ?? (string) setting('ai.image_size', '1536x1024');
        $key = trim((string) setting("ai.{$provider}_api_key", ''));

        if ($key === '') {
            throw new RuntimeException("No API key set for the image provider ({$provider}). Add it in Settings → AI settings.");
        }

        $bytes = match ($provider) {
            'gemini' => $this->imagen($key, $model, $prompt),
            default => $this->openai($key, $model, $prompt, $size, $opts['quality'] ?? (string) setting('ai.image_quality', 'medium')),
        };

        // Never accept non-image bytes (guards against an error page slipping
        // through as an "image").
        if ($bytes === '' || @getimagesizefromstring($bytes) === false) {
            throw new RuntimeException('The image provider returned data that is not a valid image.');
        }

        return ['bytes' => $bytes, 'mime' => 'image/png', 'ext' => 'png'];
    }

    /** OpenAI Images API — gpt-image-1 returns base64 directly. */
    protected function openai(string $key, string $model, string $prompt, string $size, string $quality): string
    {
        $response = Http::withToken($key)
            ->timeout(180)
            ->post('https://api.openai.com/v1/images/generations', array_filter([
                'model' => $model,
                'prompt' => $prompt,
                'size' => $size ?: '1536x1024',
                'quality' => $quality ?: null,
                'n' => 1,
            ], fn ($v) => $v !== null))
            ->throw()
            ->json();

        $b64 = $response['data'][0]['b64_json'] ?? null;

        if (! $b64) {
            Log::channel('ai')->error('[image/openai] no b64_json in response', ['keys' => array_keys((array) $response)]);
            throw new RuntimeException('OpenAI image response contained no image data.');
        }

        return (string) base64_decode($b64, true);
    }

    /** Google Imagen (Generative Language API) — predict endpoint, base64 out. */
    protected function imagen(string $key, string $model, string $prompt): string
    {
        $response = Http::withHeaders(['x-goog-api-key' => $key])
            ->timeout(180)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:predict", [
                'instances' => [['prompt' => $prompt]],
                'parameters' => ['sampleCount' => 1],
            ])
            ->throw()
            ->json();

        $b64 = $response['predictions'][0]['bytesBase64Encoded'] ?? null;

        if (! $b64) {
            Log::channel('ai')->error('[image/imagen] no image in response', ['keys' => array_keys((array) $response)]);
            throw new RuntimeException('Imagen response contained no image data.');
        }

        return (string) base64_decode($b64, true);
    }
}
