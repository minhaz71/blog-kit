<?php

namespace App\Services\Seo;

use App\Models\ProductImage;
use App\Services\Ai\LlmClient;
use Illuminate\Support\Collection;

/**
 * AI alt/title/caption writer for product images. The model never sees the
 * image bytes — it works from the filename + the product's own data (name,
 * brand, category, short description), which is exactly what the text
 * should describe anyway. One batched call per 15 images keeps token cost
 * low; results are validated against ImageSeoRules before saving.
 *
 * Provider + model are chosen in the Image SEO UI and persisted in
 * settings (seo.image_ai_provider / seo.image_ai_model).
 */
class ImageSeoWriter
{
    public const BATCH = 15;

    protected const SYSTEM = "You write image SEO metadata for an ecommerce store.\n\n"
        .ImageSeoRules::RULEBOOK
        ."\n\nPUNCTUATION: never use em dashes (—) or en dashes (–); use commas or parentheses. Never use AI-cliche words (seamless, elevate, meticulously, cutting-edge); plain natural wording only."
        ."\n\nFor EVERY image in the input, return alt, title and caption following the rules above."
        ."\nReturn ONLY JSON: {\"images\": [{\"id\": <id>, \"alt\": \"…\", \"title\": \"…\", \"caption\": \"…\"}]} — one entry per input image, nothing else.";

    /**
     * Generate metadata for the given images (missing fields only unless
     * $overwrite). Returns how many images were updated.
     *
     * @param  Collection<int, ProductImage>  $images
     */
    public function generate(Collection $images, ?string $provider = null, ?string $model = null, bool $overwrite = false): int
    {
        $provider = $provider ?: (string) setting('seo.image_ai_provider', 'anthropic');
        $model = $model ?: ((string) setting('seo.image_ai_model') ?: null);

        $updated = 0;

        // Normalize to an Eloquent collection so relations can be eager-loaded
        // regardless of how the caller assembled the set.
        $images = \Illuminate\Database\Eloquent\Collection::make($images->values()->all())
            ->loadMissing('product.brand', 'product.categories');

        foreach ($images->chunk(self::BATCH) as $chunk) {
            $payload = $chunk->map(fn (ProductImage $image) => array_filter([
                'id' => $image->id,
                'filename' => pathinfo($image->path, PATHINFO_BASENAME),
                'product' => $image->product?->name,
                'brand' => $image->product?->brand?->name,
                'category' => $image->product?->categories?->first()?->name,
                'about' => str(strip_tags((string) $image->product?->short_description))->limit(150)->toString() ?: null,
                'position_in_gallery' => (int) $image->sort_order,
                'current_alt' => $overwrite ? null : ($image->alt ?: null),
            ]))->values()->all();

            $llm = LlmClient::for($provider, $model)->withContext('image-seo');

            $parsed = LlmClient::parseJson($llm->complete(
                self::SYSTEM,
                "Images:\n".json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\nReturn the JSON now.",
                maxTokens: 4096,
                cacheStatic: true,
            ));

            $byId = collect($parsed['images'] ?? [])->keyBy('id');

            foreach ($chunk as $image) {
                $result = $byId->get($image->id);

                if (! $result) {
                    continue;
                }

                $data = array_filter([
                    'alt' => $this->clean($result['alt'] ?? null, ImageSeoRules::ALT_MAX),
                    'title' => $this->clean($result['title'] ?? null, ImageSeoRules::TITLE_MAX),
                    'caption' => $this->clean($result['caption'] ?? null, ImageSeoRules::CAPTION_MAX),
                ]);

                if (! $overwrite) {
                    // Fill blanks only — never clobber hand-written text.
                    $data = array_diff_key($data, array_filter([
                        'alt' => trim((string) $image->alt),
                        'title' => trim((string) $image->title),
                        'caption' => trim((string) $image->caption),
                    ]));
                }

                if ($data !== []) {
                    $image->update($data);
                    $updated++;
                }
            }
        }

        return $updated;
    }

    /** Enforce rulebook limits + strip banned prefixes defensively. */
    protected function clean(?string $value, int $max): ?string
    {
        $value = trim((string) \App\Services\Ai\ContentReviewer::stripEmDashes((string) $value));

        if ($value === '') {
            return null;
        }

        foreach (ImageSeoRules::BANNED_PREFIXES as $prefix) {
            if (str_starts_with(mb_strtolower($value), $prefix)) {
                $value = trim(mb_substr($value, mb_strlen($prefix)), " :,-");
                $value = ucfirst($value);
                break;
            }
        }

        return mb_substr($value, 0, $max);
    }
}
