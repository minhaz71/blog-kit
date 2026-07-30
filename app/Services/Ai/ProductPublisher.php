<?php

namespace App\Services\Ai;

use App\Models\AiActivityLog;
use App\Models\AiImportItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a reviewed AI output + CSV row into a real product: pricing per
 * batch policy, CSS auto-separated into custom_css (via the model trait
 * and the dedicated css field), SEO meta, and specs.
 *
 * The whole write is one DB transaction and is idempotent: a retried item
 * that already created its product returns it instead of duplicating.
 * Images are attached SEPARATELY via attachImage() — only after the copy
 * has passed review and the product exists (never for held/failed items).
 */
class ProductPublisher
{
    public function __construct(protected DriveImageFetcher $images = new DriveImageFetcher) {}

    public function publish(AiImportItem $item, array $output): Product
    {
        // Refresh batch: rewrite the EXISTING product's copy in place.
        // Commerce data (price, sku, stock, relations) and the slug/URL are
        // preserved — only the written content + SEO meta + FAQs change.
        if ($item->batch->refresh && $item->product_id && ($target = Product::find($item->product_id))) {
            return $this->refreshExisting($target, $item, $output);
        }

        // Idempotency: a crashed-then-retried item must not publish twice.
        if ($item->product_id && ($existing = Product::withTrashed()->find($item->product_id))) {
            AiActivityLog::write($item->batch_id, $item->id, 'publish',
                "Product #{$existing->id} already exists for this item — reusing it (no duplicate).", 'info');
            $item->update(['status' => 'published']);

            return $existing;
        }

        $batch = $item->batch;
        $row = $item->row;

        $name = trim((string) ($row['name'] ?? $row['product_name'] ?? 'Untitled product'));
        $regular = self::money($row['regular_price'] ?? $row['price'] ?? 0);
        $sale = self::money($row['sale_price'] ?? null);

        if ($batch->price_mode === 'ai' && ! empty($output['suggested_price'])) {
            $suggested = self::money($output['suggested_price']);
            // AI adjusts the sale price; the regular price stays an anchor.
            $sale = ($regular > 0 && $suggested < $regular) ? $suggested : $sale;
            $regular = max($regular, $suggested);
        }

        // The slug was reserved at parse time so the whole batch could link
        // to this URL contextually. Only re-suffix on a genuine collision
        // (someone created that slug mid-batch) — finalize cleans any
        // sibling links that pointed at the old URL.
        $slug = $item->reserved_slug ?: Str::slug($name);
        if (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
            AiActivityLog::write($batch->id, $item->id, 'publish',
                "Reserved slug was taken in the meantime — published under \"{$slug}\"; links to the old URL are removed in the final pass.", 'warning');
        }

        // Product + FAQs + SEO meta land together or not at all — a crash
        // mid-way must not leave an orphaned half-configured live product.
        return DB::transaction(function () use ($item, $output, $batch, $row, $name, $slug, $regular, $sale): Product {
            // <style> blocks inside description_html are ALSO handled by the
            // MovesInlineStylesToCustomCss trait — both paths converge.
            $product = Product::create([
                'name' => $name,
                'slug' => $slug,
                'type' => 'simple',
                'price' => $regular,
                'sale_price' => $sale > 0 && $sale < $regular ? $sale : null,
                'sku' => $row['sku'] ?? null,
                'short_description' => \App\Support\HtmlSanitizer::clean($output['short_description_html'] ?? ''),
                'description' => \App\Support\HtmlSanitizer::clean($output['description_html'] ?? ''),
                'custom_css' => trim((string) ($output['css'] ?? '')) ?: null,
                'specifications' => self::specs($row['specifications'] ?? $row['specs'] ?? null),
                'stock_status' => 'in_stock',
                'manage_stock' => false,
                'visibility' => 'visible',
                'status' => $batch->publish_mode === 'publish' ? 'published' : 'draft',
            ]);

            // Brand + category from the CSV land on the real catalog
            // relations (created on first use), not just in prompt context.
            if (($brandName = trim((string) ($row['brand'] ?? ''))) !== '') {
                $brand = \App\Models\Brand::firstOrCreate(
                    ['slug' => Str::slug($brandName)],
                    ['name' => $brandName, 'is_active' => true],
                );
                $product->update(['brand_id' => $brand->id]);
            }

            // category_id pins the product to EXACT existing categories —
            // no typo-driven duplicates, works even after a category is
            // renamed. Unknown IDs are skipped with a warning, never
            // silently created.
            $categoryIds = collect(preg_split('/[|,;]+/', (string) ($row['category_id'] ?? '')))
                ->map(fn ($id) => (int) trim($id))
                ->filter()
                ->unique();

            foreach ($categoryIds as $categoryId) {
                if (\App\Models\Category::whereKey($categoryId)->exists()) {
                    $product->categories()->syncWithoutDetaching([$categoryId]);
                } else {
                    \App\Models\AiActivityLog::write($item->batch_id, $item->id, 'publish',
                        "category_id {$categoryId} does not exist — skipped. Check Catalog → Categories for the real IDs.", 'warning');
                }
            }

            // Category NAMES resolve second (created on first use) — the
            // simple-CSV path where the owner just types the category.
            $categoryNames = collect(preg_split('/[|,;]+/', (string) ($row['category'] ?? '')))
                ->map(fn ($c) => trim($c))
                ->filter();

            foreach ($categoryNames as $categoryName) {
                $category = \App\Models\Category::firstOrCreate(
                    ['slug' => Str::slug($categoryName)],
                    ['name' => $categoryName, 'is_active' => true],
                );
                $product->categories()->syncWithoutDetaching([$category->id]);
            }

            self::resolveAttributes($product, $item, (array) ($output['attributes'] ?? []));

            // FAQ pairs power the on-page FAQ section + automatic FAQPage
            // schema. The writer is asked for 6-10 — keep them all.
            foreach (array_slice((array) ($output['faqs'] ?? []), 0, 10) as $index => $faq) {
                $question = trim((string) ($faq['question'] ?? ''));
                $answer = trim((string) ($faq['answer'] ?? ''));

                if ($question !== '' && $answer !== '') {
                    $product->faqs()->create([
                        'question' => $question,
                        'answer' => $answer,
                        'sort_order' => $index,
                        'is_active' => true,
                    ]);
                }
            }

            // focus_keyword: the store owner's primary CSV keyword wins over
            // an empty/AI-invented one — it is the SEO plan for this page.
            $primaryKeyword = ProductWriter::keywordsFor($row)[0] ?? '';

            // secondary_keywords feed the Link Agent's anchor vocabulary
            // (LinkDictionary reads seoMeta->secondary_keywords) — cleaned,
            // deduped, capped so a chatty model can't flood the dictionary.
            $secondary = collect((array) ($output['secondary_keywords'] ?? []))
                ->map(fn ($kw) => trim((string) $kw))
                ->filter(fn ($kw) => $kw !== '' && mb_strlen($kw) <= 60)
                ->unique()
                ->take(5)
                ->values()
                ->all();

            $product->seoMeta()->updateOrCreate([], [
                'title' => mb_substr((string) ($output['meta_title'] ?? $name), 0, 60),
                'description' => mb_substr((string) ($output["meta_description"] ?? ""), 0, 164),
                'focus_keyword' => trim((string) ($output['focus_keyword'] ?? '')) ?: $primaryKeyword,
                'secondary_keywords' => $secondary,
                'schema_enabled' => true,
            ]);

            $item->update(['product_id' => $product->id, 'status' => 'published']);

            return $product;
        });
    }

    /**
     * Rewrite an existing product's copy in place (refresh batch). Preserves
     * name, slug/URL, price, sku, stock, visibility and catalog relations —
     * only the description, short description, css, SEO meta and FAQs are
     * replaced. FAQs are swapped wholesale (a re-run must not stack them).
     */
    protected function refreshExisting(Product $product, AiImportItem $item, array $output): Product
    {
        return DB::transaction(function () use ($product, $item, $output): Product {
            $product->forceFill([
                'short_description' => \App\Support\HtmlSanitizer::clean($output['short_description_html'] ?? ''),
                'description' => \App\Support\HtmlSanitizer::clean($output['description_html'] ?? ''),
                'custom_css' => trim((string) ($output['css'] ?? '')) ?: $product->custom_css,
            ])->save();

            $secondary = collect((array) ($output['secondary_keywords'] ?? []))
                ->map(fn ($kw) => trim((string) $kw))
                ->filter(fn ($kw) => $kw !== '' && mb_strlen($kw) <= 60)
                ->unique()->take(5)->values()->all();

            $product->seoMeta()->updateOrCreate([], array_filter([
                'title' => mb_substr((string) ($output['meta_title'] ?? ''), 0, 60),
                'description' => mb_substr((string) ($output['meta_description'] ?? ''), 0, 164),
                'focus_keyword' => trim((string) ($output['focus_keyword'] ?? '')),
                'secondary_keywords' => $secondary ?: null,
                'schema_enabled' => true,
            ], fn ($v) => $v !== '' && $v !== null));

            // Replace FAQs wholesale.
            $product->faqs()->delete();
            foreach (array_slice((array) ($output['faqs'] ?? []), 0, 10) as $index => $faq) {
                $q = trim((string) ($faq['question'] ?? ''));
                $a = trim((string) ($faq['answer'] ?? ''));
                if ($q !== '' && $a !== '') {
                    $product->faqs()->create(['question' => $q, 'answer' => $a, 'sort_order' => $index, 'is_active' => true]);
                }
            }

            $item->update(['status' => 'published']);
            AiActivityLog::write($item->batch_id, $item->id, 'publish',
                "♻ Refreshed \"{$product->name}\" — copy rewritten, price/URL/stock preserved.", 'success');

            return $product;
        });
    }

    /**
     * Attach the product image — called AFTER review approval and a
     * successful publish, never before. Images come ONLY from the batch's
     * Drive folder (set in the form; optional): the folder is listed once
     * per batch and each product gets the image whose filename best matches
     * its name. CSV image columns are ignored. Failures are reported, not
     * fatal: a missing image must not sink a published product.
     */
    public function attachImage(AiImportItem $item, Product $product, array $output): bool
    {
        $meta = [
            'alt' => (string) ($output['image_alt'] ?? $product->name),
            'title' => (string) ($output['image_title'] ?? '') ?: null,
            'caption' => (string) ($output['image_caption'] ?? '') ?: null,
        ];

        try {
            if (! $item->batch->drive_folder) {
                return false; // no folder configured — images are optional
            }

            $fetched = $this->images->fetch($product, $item->batch->drive_folder, $meta);

            if ($fetched) {
                AiActivityLog::write($item->batch_id, $item->id, 'image',
                    "🖼 Image attached to \"{$product->name}\" (alt, title, caption set).", 'success');
            } else {
                AiActivityLog::write($item->batch_id, $item->id, 'image',
                    "No folder image matched \"{$product->name}\" closely enough — product kept without an image. Name a file after the product (e.g. \"amber kazakhstan.jpg\") and use the item's Re-run action.", 'warning');
            }

            return $fetched;
        } catch (\Throwable $e) {
            $item->update(['error' => 'Image fetch failed: '.mb_substr($e->getMessage(), 0, 500)]);
            AiActivityLog::write($item->batch_id, $item->id, 'image',
                "Image fetch failed for \"{$product->name}\": ".$e->getMessage().' — product kept without an image.', 'warning');

            return false;
        }
    }

    /**
     * Parse a price from any common locale format:
     * "AED 1,299.00" → 1299.00 · "29,99" → 29.99 · "1.299,00" → 1299.00.
     */
    public static function money(mixed $value): float
    {
        $s = preg_replace('/[^\d.,-]/', '', (string) $value);

        if ($s === '' || $s === '-' || $s === null) {
            return 0.0;
        }

        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');

        if ($lastComma !== false && $lastDot !== false) {
            // Both present → the LAST separator is the decimal point.
            [$decimal, $thousands] = $lastComma > $lastDot ? [',', '.'] : ['.', ','];
            $s = str_replace($thousands, '', $s);
            $s = str_replace($decimal, '.', $s);
        } elseif ($lastComma !== false) {
            // Comma only: decimal when followed by 1-2 digits ("29,99"),
            // thousands otherwise ("1,299").
            $digitsAfter = strlen($s) - $lastComma - 1;
            $s = $digitsAfter <= 2 && substr_count($s, ',') === 1
                ? str_replace(',', '.', $s)
                : str_replace(',', '', $s);
        }

        return round((float) $s, 2);
    }

    /**
     * Attach the AI's structured facts (semantic SEO) to the real
     * Attribute/AttributeValue taxonomy. Only the canonical facets seeded by
     * TereaAttributeSeeder are recognized — an unmatched key is a free-form
     * field the AI invented and is ignored, not turned into a new Attribute.
     * An unmatched VALUE under a known facet is created but flagged
     * `needs_review` so the vocabulary stays admin-controlled without
     * silently losing a fact the AI correctly identified.
     */
    protected static function resolveAttributes(Product $product, AiImportItem $item, array $attributesOutput): void
    {
        foreach ($attributesOutput as $key => $rawValue) {
            if ($rawValue === null || $rawValue === '') {
                continue;
            }

            $attribute = \App\Models\Attribute::where('slug', str_replace('_', '-', $key))->first();

            if (! $attribute) {
                continue;
            }

            foreach ((array) $rawValue as $valueString) {
                $valueString = trim((string) $valueString);

                if ($valueString === '') {
                    continue;
                }

                $slug = Str::slug($valueString);
                $attributeValue = \App\Models\AttributeValue::where('attribute_id', $attribute->id)->where('slug', $slug)->first();

                if (! $attributeValue) {
                    $attributeValue = \App\Models\AttributeValue::create([
                        'attribute_id' => $attribute->id,
                        'value' => $valueString,
                        'slug' => $slug,
                        'needs_review' => true,
                    ]);

                    AiActivityLog::write($item->batch_id, $item->id, 'attributes',
                        "New {$attribute->name} value \"{$valueString}\" was not in the vocabulary — auto-created and flagged for review.", 'warning');
                }

                $product->attributes()->syncWithoutDetaching([$attribute->id => ['is_variation' => false, 'is_visible' => true]]);
                $product->attributeValues()->syncWithoutDetaching([$attributeValue->id]);
            }
        }
    }

    /** Parse "Key: Value | Key: Value" or "Key: Value" lines into a specs map. */
    protected static function specs(?string $raw): ?array
    {
        if (! $raw || trim($raw) === '') {
            return null;
        }

        $pairs = [];
        foreach (preg_split('/[|;\n]+/', $raw) as $chunk) {
            if (str_contains($chunk, ':')) {
                [$k, $v] = array_map('trim', explode(':', $chunk, 2));
                if ($k !== '' && $v !== '') {
                    $pairs[$k] = $v;
                }
            }
        }

        return $pairs ?: null;
    }
}
