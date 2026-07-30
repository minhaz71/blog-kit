<?php

namespace App\Services\Ai;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Finds a product's image in a Google Drive folder by name matching,
 * downloads it, renames it to the product slug, stores it as the
 * featured image + gallery entry with alt/title/caption metadata.
 *
 * Every download is validated before it touches disk: the response must
 * be a real image (Content-Type image/*, non-empty, under the size cap) —
 * Google's public endpoint returns an HTML confirmation page for large
 * files, which must never become a product image.
 *
 * Works with a Drive API key and a folder that is shared "anyone with
 * the link can view". Accepts either a raw folder ID or a full URL.
 */
class DriveImageFetcher
{
    /** Refuse downloads larger than this (bytes) — 20 MB. */
    public const MAX_BYTES = 20 * 1024 * 1024;

    /** How deep to recurse into subfolders (root folder is depth 0). */
    public const MAX_DEPTH = 6;

    /** Safety cap so a mis-shared root can't crawl an entire Drive. */
    public const MAX_FOLDERS = 300;

    public function fetch(Product $product, string $folder, array $meta = []): bool
    {
        $apiKey = (string) setting('ai.google_drive_api_key');

        if ($apiKey === '' || ($folderId = self::folderId($folder)) === '') {
            return false;
        }

        $file = $this->bestMatch($product->name, $folderId, $apiKey);

        if ($file === null) {
            return false;
        }

        $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
            ->timeout(60)
            ->get("https://www.googleapis.com/drive/v3/files/{$file['id']}", ['alt' => 'media'])
            ->throw();

        // The Drive file's OWN name becomes the permalink slug —
        // "terea kazakhstan amber.jpg" → terea-kazakhstan-amber.jpg.
        return $this->store($product, $response->body(), (string) $response->header('Content-Type'), $meta, $file['name'] ?? null);
    }

    /**
     * Per-row image link: a Google Drive file link (uses the public
     * download endpoint, no API key needed) or any direct image URL.
     */
    public function fetchFromLink(Product $product, string $link, array $meta = []): bool
    {
        $link = trim($link);

        if ($link === '') {
            return false;
        }

        if (($fileId = self::driveFileId($link)) !== null) {
            $apiKey = (string) setting('ai.google_drive_api_key');

            // API key rides in a header, never the URL (URLs end up in logs).
            $response = $apiKey !== ''
                ? Http::withHeaders(['x-goog-api-key' => $apiKey])->timeout(60)
                    ->get("https://www.googleapis.com/drive/v3/files/{$fileId}", ['alt' => 'media'])->throw()
                : Http::timeout(60)
                    ->get('https://drive.google.com/uc', ['export' => 'download', 'id' => $fileId])->throw();
        } else {
            $response = Http::timeout(60)->get($link)->throw();
        }

        // Direct URLs keep their own basename as the permalink slug; Drive
        // share links carry no real filename ("/view"), so fall back to the
        // product slug there.
        $originalName = $fileId === null ? basename((string) parse_url($link, PHP_URL_PATH)) : null;

        return $this->store($product, $response->body(), (string) $response->header('Content-Type'), $meta, $originalName);
    }

    /**
     * Validate + persist a downloaded image and attach it to the product.
     * Throws with a clear reason when the payload is not a usable image, so
     * the caller can surface it (a wrong image is worse than no image).
     */
    protected function store(Product $product, string $binary, string $contentType, array $meta, ?string $originalName = null): bool
    {
        $contentType = strtolower(trim(explode(';', $contentType)[0]));

        // Google Drive returns text/html (a confirmation/queue page) for
        // large or restricted files — that must never be saved as a .jpg.
        if (! str_starts_with($contentType, 'image/')) {
            Log::channel('ai')->warning("Image rejected for \"{$product->name}\": Content-Type \"{$contentType}\" is not an image.");

            throw new RuntimeException(
                "Download was not an image (Content-Type: {$contentType}). "
                .'For Google Drive, make sure the file is shared "anyone with the link" and small enough for direct download, or configure a Drive API key.'
            );
        }

        if ($binary === '' || strlen($binary) > self::MAX_BYTES) {
            throw new RuntimeException(
                $binary === ''
                    ? 'Image download was empty.'
                    : 'Image exceeds the 20 MB limit — resize it before importing.'
            );
        }

        // Second opinion from the bytes themselves — cheap and decisive.
        if (@getimagesizefromstring($binary) === false) {
            throw new RuntimeException('Downloaded file is not a valid image (corrupt or unsupported format).');
        }

        $extension = match ($contentType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        // Permalink = slug of the ORIGINAL file name ("terea kazakhstan
        // amber.jpg" → terea-kazakhstan-amber.jpg), defined once here.
        // Falls back to the product slug when no usable name came through.
        $slug = \App\Services\Seo\ImageSeoRules::slugFromOriginalName((string) $originalName, $product->slug);
        $path = \App\Services\Seo\ImageSeoRules::uniquePath('products', $slug, $extension);
        Storage::disk('public')->put($path, $binary);

        // Create the media record FIRST — the ProductObserver auto-creates a
        // blank record for any new featured image path, and must find this
        // one (with the real alt/title/caption) already in place.
        $product->images()->create([
            'path' => $path,
            'alt' => $meta['alt'] ?? $product->name,
            'title' => $meta['title'] ?? null,
            'caption' => $meta['caption'] ?? null,
            'sort_order' => 0,
        ]);

        $product->update(['featured_image' => $path]);

        return true;
    }

    /** Extract a Drive FILE id from /file/d/{id}, open?id= or id= links. */
    public static function driveFileId(string $link): ?string
    {
        if (preg_match('~/file/d/([a-zA-Z0-9_-]{10,})~', $link, $m)) {
            return $m[1];
        }

        if (preg_match('~[?&]id=([a-zA-Z0-9_-]{10,})~', $link, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Pick the folder image whose FILENAME best matches the product name.
     *
     * The whole folder is listed (no server-side name filter — filenames
     * rarely contain the full product name; "amber kazakhstan.jpg" must
     * match "IQOS Terea Amber Kazakhstan") and scored locally by token
     * overlap:
     *  - every filename token should belong to the product name (precision
     *    ≥ 0.5, so "amber blue.jpg" never wins for "Terea Amber"),
     *  - more matched tokens win ("amber kazakhstan" beats "amber"),
     *  - similar_text breaks remaining ties.
     * No file passes the bar → no image (wrong image is worse than none).
     */
    protected function bestMatch(string $productName, string $folderId, string $apiKey): ?array
    {
        $files = $this->listImages($folderId, $apiKey);

        if ($files === []) {
            return null;
        }

        $productTokens = self::tokens($productName);

        $best = collect($files)
            ->map(function (array $file) use ($productTokens): ?array {
                $fileTokens = self::tokens(pathinfo($file['name'], PATHINFO_FILENAME));

                if ($fileTokens === []) {
                    return null;
                }

                $matched = count(array_intersect($fileTokens, $productTokens));
                $precision = $matched / count($fileTokens);

                // Strictly more than half of the filename must belong to this
                // product: "terea sienna.jpg" (1 of 2 generic tokens matched)
                // must NOT attach to "Terea Ruby". Single-token files
                // ("amber.jpg") are fine — 1/1 passes.
                if ($matched === 0 || $precision <= 0.5) {
                    return null;
                }

                similar_text(implode(' ', $productTokens), implode(' ', $fileTokens), $percent);

                return ['file' => $file, 'score' => $matched * 1000 + (int) ($precision * 100) + (int) $percent];
            })
            ->filter()
            ->sortByDesc('score')
            ->first();

        return $best['file'] ?? null;
    }

    /**
     * Every image in the folder AND all of its subfolders (breadth-first,
     * depth-capped). Cached for 10 minutes so a batch of N products walks the
     * tree once, not N times.
     *
     * @return array<int, array{id: string, name: string}>
     */
    protected function listImages(string $folderId, string $apiKey): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "drive-images:{$folderId}",
            600,
            function () use ($folderId, $apiKey): array {
                $images = [];
                $visited = [];
                $foldersScanned = 0;
                $queue = [[$folderId, 0]]; // [folderId, depth]

                while ($queue !== []) {
                    [$currentId, $depth] = array_shift($queue);

                    if (isset($visited[$currentId])) {
                        continue; // shortcuts / duplicate parents — never scan twice
                    }
                    $visited[$currentId] = true;

                    if (++$foldersScanned > self::MAX_FOLDERS) {
                        Log::channel('ai')->warning(
                            'Drive scan hit the '.self::MAX_FOLDERS."-folder cap for {$folderId}; deeper subfolders were skipped."
                        );
                        break;
                    }

                    [$found, $subfolders] = $this->listChildren($currentId, $apiKey);
                    $images = array_merge($images, $found);

                    if ($depth < self::MAX_DEPTH) {
                        foreach ($subfolders as $sub) {
                            $queue[] = [$sub['id'], $depth + 1];
                        }
                    }
                }

                return $images;
            },
        );
    }

    /**
     * One folder's direct children, split into image files and subfolders
     * (paginated). Subfolders are recursed into by listImages().
     *
     * @return array{0: array<int, array{id: string, name: string}>, 1: array<int, array{id: string, name: string}>}
     */
    protected function listChildren(string $folderId, string $apiKey): array
    {
        $images = [];
        $folders = [];
        $pageToken = null;

        do {
            $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout(30)
                ->get('https://www.googleapis.com/drive/v3/files', array_filter([
                    'q' => "'{$folderId}' in parents and trashed = false and "
                        ."(mimeType contains 'image/' or mimeType = 'application/vnd.google-apps.folder')",
                    'fields' => 'nextPageToken, files(id,name,mimeType)',
                    'pageSize' => 1000,
                    'pageToken' => $pageToken,
                ]))
                ->throw()
                ->json();

            foreach ($response['files'] ?? [] as $file) {
                if (($file['mimeType'] ?? '') === 'application/vnd.google-apps.folder') {
                    $folders[] = $file;
                } else {
                    $images[] = $file;
                }
            }

            $pageToken = $response['nextPageToken'] ?? null;
        } while ($pageToken !== null);

        return [$images, $folders];
    }

    /** Lowercased alphanumeric words (≥2 chars), deduplicated. */
    protected static function tokens(string $value): array
    {
        return collect(preg_split('/[^a-z0-9]+/', Str::lower($value)) ?: [])
            ->filter(fn ($t) => mb_strlen($t) >= 2)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Verify the Drive API key (and optionally a folder) actually works.
     *
     * @return array{0: bool, 1: string}
     */
    public static function healthCheck(?string $folder = null): array
    {
        $apiKey = (string) setting('ai.google_drive_api_key');

        if ($apiKey === '') {
            return [false, 'No Google Drive API key configured (Settings → AI settings) — folder image matching is disabled.'];
        }

        try {
            if ($folder !== null && trim($folder) !== '') {
                $files = (new self)->listImages(self::folderId($folder), $apiKey);

                if ($files === []) {
                    return [false, 'Drive API key works, but the folder shows 0 images — check the folder is shared "anyone with the link can view" and actually contains images.'];
                }

                return [true, 'Drive API OK — '.count($files).' image(s) visible in the folder.'];
            }

            $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout(15)
                ->get('https://www.googleapis.com/drive/v3/files', ['pageSize' => 1, 'fields' => 'files(id)']);

            // API-key auth cannot list private files, so a bare listing 403s
            // even with a PERFECTLY VALID key — only a 400 "API key not
            // valid" means the key is actually broken.
            if ($response->status() === 400) {
                return [false, 'Drive API key is INVALID (Google returned "API key not valid"). Create a key with the Drive API enabled in Google Cloud Console.'];
            }

            return [true, 'Drive API key is valid. Set a shared folder on the batch to verify end-to-end (the key can only read folders shared "anyone with the link").'];
        } catch (\Throwable $e) {
            return [false, 'Drive API check failed: '.mb_substr($e->getMessage(), 0, 300)];
        }
    }

    public static function folderId(string $folder): string
    {
        if (preg_match('~folders/([a-zA-Z0-9_-]+)~', $folder, $m)) {
            return $m[1];
        }

        return trim($folder);
    }
}
