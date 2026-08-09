<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Single-request text-to-image generation for blog thumbnails. ONE call per
 * image, no revision/refinement loop — the cheapest reliable path. Reuses the
 * same provider API keys as the text writer (ai.{provider}_api_key), plus a
 * dedicated key for fal.ai (ai.fal_api_key).
 *
 * Lowest cost: fal.ai "FLUX.1 [schnell]" (~$0.003/image, a few seconds).
 * Best prompt adherence: OpenAI "gpt-image-1". Google offers the cheap
 * "gemini-2.5-flash-image" or the higher-fidelity "imagen-3.0-generate-002".
 */
class ImageGenerator
{
    /** provider => default image model. */
    public const DEFAULT_MODELS = [
        'fal' => 'fal-ai/flux/schnell',
        'openai' => 'gpt-image-1',
        'gemini' => 'gemini-2.5-flash-image',
    ];

    /** provider => a short menu of models the admin can pick (value => label). */
    public const MODELS = [
        'fal' => [
            'fal-ai/flux/schnell' => 'FLUX.1 [schnell] — lowest cost, ~4s (recommended)',
            'fal-ai/flux/dev' => 'FLUX.1 [dev] — higher quality, a bit pricier',
            'fal-ai/fast-sdxl' => 'Fast SDXL — cheapest, classic Stable Diffusion',
        ],
        'openai' => [
            'gpt-image-1' => 'gpt-image-1 — best prompt adherence',
            'dall-e-3' => 'DALL·E 3 — cheaper, still strong',
        ],
        'gemini' => [
            'gemini-2.5-flash-image' => 'Gemini 2.5 Flash Image — low cost, fast',
            'imagen-3.0-generate-002' => 'Imagen 3 — highest fidelity',
        ],
    ];

    public const PROVIDER_LABELS = [
        'fal' => 'fal.ai — FLUX.1 schnell (lowest cost, recommended)',
        'openai' => 'OpenAI — gpt-image-1 / DALL·E 3',
        'gemini' => 'Google — Gemini Flash Image / Imagen 3',
    ];

    /**
     * Approximate USD cost PER IMAGE, by model — image APIs bill per image, not
     * per token, so this is how we attribute thumbnail spend in AI cost reports.
     * Estimates (list prices as of early 2026); tune in one place if they change.
     */
    public const IMAGE_PRICES = [
        'fal-ai/flux/schnell' => 0.003,
        'fal-ai/flux/dev' => 0.025,
        'fal-ai/fast-sdxl' => 0.002,
        'gpt-image-1' => 0.04,
        'dall-e-3' => 0.04,
        'gemini-2.5-flash-image' => 0.039,
        'imagen-3.0-generate-002' => 0.04,
    ];

    /** Best-effort per-image price for the given (or configured) provider/model. */
    public static function costFor(?string $provider = null, ?string $model = null): float
    {
        $model ??= self::model($provider);

        return self::IMAGE_PRICES[$model] ?? 0.0;
    }

    public static function provider(): string
    {
        $p = (string) setting('ai.image_provider', 'fal');

        return isset(self::DEFAULT_MODELS[$p]) ? $p : 'fal';
    }

    public static function model(?string $provider = null): string
    {
        $provider ??= self::provider();
        $model = trim((string) setting('ai.image_model', ''));

        return $model !== '' ? $model : self::DEFAULT_MODELS[$provider];
    }

    /** The API-key setting a provider reads (fal has its own key). */
    public static function keyName(?string $provider = null): string
    {
        return 'ai.'.($provider ?? self::provider()).'_api_key';
    }

    /** True when the configured image provider has an API key set. */
    public static function isConfigured(): bool
    {
        return trim((string) setting(self::keyName(), '')) !== '';
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
        // Default to a true 16:9 hero (matches the blog card/hero slot, so the
        // image isn't cropped top/bottom by object-cover). 1536x864 = 16:9.
        $size = $opts['size'] ?? (string) setting('ai.image_size', '1536x864');
        $key = trim((string) setting(self::keyName($provider), ''));

        if ($key === '') {
            throw new RuntimeException("No API key set for the image provider ({$provider}). Add it in Settings → AI settings.");
        }

        $bytes = match ($provider) {
            'fal' => $this->fal($key, $model, $prompt, $size),
            'gemini' => str_starts_with($model, 'imagen')
                ? $this->imagen($key, $model, $prompt)
                : $this->geminiFlashImage($key, $model, $prompt),
            default => $this->openai($key, $model, $prompt, $size, $opts['quality'] ?? (string) setting('ai.image_quality', 'medium')),
        };

        // Never accept non-image bytes (guards against an error page slipping
        // through as an "image").
        if ($bytes === '' || @getimagesizefromstring($bytes) === false) {
            throw new RuntimeException('The image provider returned data that is not a valid image.');
        }

        [$mime, $ext] = $this->sniff($bytes);

        return ['bytes' => $bytes, 'mime' => $mime, 'ext' => $ext];
    }

    /** Parse "1536x1024" into [width, height], with a sane landscape default. */
    protected function dimensions(string $size): array
    {
        if (preg_match('/^(\d{2,5})\s*x\s*(\d{2,5})$/i', trim($size), $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [1536, 864]; // 16:9 landscape default
    }

    /** Round a dimension to the nearest multiple of 16 (FLUX/SDXL like this). */
    protected function snap16(int $n): int
    {
        return max(256, (int) (round($n / 16) * 16));
    }

    /** Detect the real image type from magic bytes (fal may return JPEG/WebP). */
    protected function sniff(string $bytes): array
    {
        $info = @getimagesizefromstring($bytes);
        $mime = $info['mime'] ?? 'image/png';

        return [$mime, match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'png',
        }];
    }

    /**
     * fal.ai — FLUX.1 / SDXL family. The cheapest popular path. Returns an
     * image URL, which we then download to raw bytes.
     */
    protected function fal(string $key, string $model, string $prompt, string $size): string
    {
        [$w, $h] = $this->dimensions($size);
        $w = $this->snap16($w);
        $h = $this->snap16($h);

        $response = Http::withHeaders(['Authorization' => 'Key '.$key])
            ->timeout(180)
            ->post('https://fal.run/'.ltrim($model, '/'), [
                'prompt' => $prompt,
                'image_size' => ['width' => $w, 'height' => $h],
                'num_images' => 1,
                'enable_safety_checker' => true,
                'output_format' => 'png',
            ])
            ->throw()
            ->json();

        $url = $response['images'][0]['url'] ?? ($response['image']['url'] ?? null);

        if (! $url) {
            Log::channel('ai')->error('[image/fal] no image url in response', ['keys' => array_keys((array) $response)]);
            throw new RuntimeException('fal.ai image response contained no image.');
        }

        // fal can inline the image as a data: URI, or give an https URL.
        if (str_starts_with($url, 'data:')) {
            $comma = strpos($url, ',');

            return $comma !== false ? (string) base64_decode(substr($url, $comma + 1), true) : '';
        }

        return (string) Http::timeout(120)->get($url)->throw()->body();
    }

    /** OpenAI Images API — gpt-image-1 / dall-e-3 return base64 or a URL. */
    protected function openai(string $key, string $model, string $prompt, string $size, string $quality): string
    {
        $isDalle = str_starts_with($model, 'dall-e');

        $response = Http::withToken($key)
            ->timeout(180)
            ->post('https://api.openai.com/v1/images/generations', array_filter([
                'model' => $model,
                'prompt' => $prompt,
                // dall-e-3 only accepts a fixed set of sizes; gpt-image-1 is flexible.
                'size' => $isDalle ? '1792x1024' : ($size ?: '1536x1024'),
                'quality' => $isDalle ? null : ($quality ?: null),
                'response_format' => $isDalle ? 'b64_json' : null,
                'n' => 1,
            ], fn ($v) => $v !== null))
            ->throw()
            ->json();

        $b64 = $response['data'][0]['b64_json'] ?? null;

        if (! $b64) {
            // gpt-image-1 always returns b64; dall-e can return a URL when not asked for b64.
            $url = $response['data'][0]['url'] ?? null;
            if ($url) {
                return (string) Http::timeout(120)->get($url)->throw()->body();
            }

            Log::channel('ai')->error('[image/openai] no image in response', ['keys' => array_keys((array) $response)]);
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

    /** Google Gemini Flash Image (generateContent) — inline base64 image part. */
    protected function geminiFlashImage(string $key, string $model, string $prompt): string
    {
        $response = Http::withHeaders(['x-goog-api-key' => $key])
            ->timeout(180)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['responseModalities' => ['IMAGE']],
            ])
            ->throw()
            ->json();

        foreach ((array) ($response['candidates'][0]['content']['parts'] ?? []) as $part) {
            $b64 = $part['inlineData']['data'] ?? ($part['inline_data']['data'] ?? null);
            if ($b64) {
                return (string) base64_decode($b64, true);
            }
        }

        Log::channel('ai')->error('[image/gemini-flash] no image in response', ['keys' => array_keys((array) $response)]);
        throw new RuntimeException('Gemini Flash Image response contained no image data.');
    }
}
