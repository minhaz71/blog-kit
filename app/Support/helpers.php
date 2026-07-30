<?php

use App\Models\ContentBlock;
use App\Models\Setting;
use App\Services\Content\ShortcodeParser;

if (! function_exists('block')) {
    /** Render a named content block, or return null when it doesn't exist / is inactive. */
    function block(string $key): ?string
    {
        $block = ContentBlock::query()->where('is_active', true)->where('key', $key)->first();

        return $block?->render();
    }
}

if (! function_exists('parse_shortcodes')) {
    /** Replace {{block:key}} shortcodes with the rendered block HTML. */
    function parse_shortcodes(?string $html): string
    {
        return app(ShortcodeParser::class)->parse($html);
    }
}

if (! function_exists('safe_cache')) {
    /**
     * Like cache()->remember() but safe against Eloquent Collection rehydration
     * quirks in file/database cache stores. Prefer this over Cache::remember()
     * whenever the callback returns Eloquent models or collections.
     */
    function safe_cache(string $key, int $ttl, \Closure $callback): mixed
    {
        return \App\Services\Performance\SafeCache::remember($key, $ttl, $callback);
    }
}

if (! function_exists('setting')) {
    /** Get a setting via "group.key" dot notation. */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('module_enabled')) {
    /**
     * Whether an optional module is active. Ecommerce ships OFF in Hemdox
     * Blog Kit and is retained-but-hidden; an admin flips it in
     * Admin → System → Modules (persisted as the "modules.{name}_enabled"
     * setting). config/blogkit.php ("modules") is the fallback default used
     * when the setting was never saved — or when the DB isn't ready yet
     * (early boot, before migrations), where reading settings would throw.
     */
    function module_enabled(string $module): bool
    {
        $configDefault = (bool) config("blogkit.modules.$module", false);

        try {
            $value = setting("modules.{$module}_enabled", null);
        } catch (\Throwable) {
            return $configDefault;
        }

        if ($value === null) {
            return $configDefault;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

if (! function_exists('ecommerce_enabled')) {
    /** Convenience wrapper: is the optional ecommerce (store) module active? */
    function ecommerce_enabled(): bool
    {
        return module_enabled('ecommerce');
    }
}

if (! function_exists('pb_block_style')) {
    /**
     * Inline CSS for a product-template block from its style settings
     * (text/background colour, font size, alignment, padding). Heading
     * colour is exposed as the --pb-heading custom property so block
     * partials can tint their own headings.
     */
    function pb_block_style(array $data): string
    {
        $rules = [];

        if (! empty($data['text_color'])) {
            $rules[] = 'color:'.$data['text_color'];
        }
        if (! empty($data['bg_color'])) {
            $rules[] = 'background-color:'.$data['bg_color'];
        }
        if (! empty($data['heading_color'])) {
            $rules[] = '--pb-heading:'.$data['heading_color'];
        }

        $sizes = ['xs' => '.75rem', 'sm' => '.875rem', 'base' => '1rem', 'lg' => '1.125rem', 'xl' => '1.25rem', '2xl' => '1.5rem', '3xl' => '1.875rem'];
        if (! empty($data['font_size'])) {
            $fs = $data['font_size'];
            $rules[] = 'font-size:'.($sizes[$fs] ?? (is_numeric($fs) ? $fs.'px' : $fs));
        }

        if (! empty($data['align'])) {
            $rules[] = 'text-align:'.$data['align'];
        }

        $pads = ['sm' => '.75rem', 'md' => '1.25rem', 'lg' => '2rem'];
        if (! empty($data['padding']) && isset($pads[$data['padding']])) {
            $rules[] = 'padding:'.$pads[$data['padding']];
        }

        return implode(';', $rules);
    }
}

if (! function_exists('store_currency')) {
    function store_currency(): string
    {
        return (string) setting('general.currency', 'USD');
    }
}

if (! function_exists('store_currency_symbol')) {
    /**
     * What customers SEE next to prices. The store owner types anything in
     * settings ("AED", "د.إ", "$"); falls back to a per-currency default.
     */
    function store_currency_symbol(): string
    {
        // Returned as typed (a trailing space like "AED " is intentional);
        // trim only decides whether anything was configured at all.
        $custom = (string) setting('general.currency_symbol', '');

        if (trim($custom) !== '') {
            return $custom;
        }

        $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'BDT' => '৳', 'AED' => 'د.إ', 'SAR' => '﷼', 'INR' => '₹'];

        return $symbols[store_currency()] ?? store_currency().' ';
    }
}

if (! function_exists('store_currency_decimals')) {
    /** 0 = flat prices (30), 2 = 30.00 — the store owner's choice. */
    function store_currency_decimals(): int
    {
        return max(0, min(4, (int) setting('general.currency_decimals', 2)));
    }
}

if (! function_exists('price_format')) {
    function price_format(float|string|null $amount): string
    {
        $formatted = number_format((float) $amount, store_currency_decimals());

        return setting('general.currency_position', 'left') === 'right'
            ? $formatted.' '.store_currency_symbol()
            : store_currency_symbol().$formatted;
    }
}

if (! function_exists('store_countries')) {
    /**
     * Countries this store sells/ships to, [ISO code => name]. WooCommerce
     * style: "all" = every country; "specific" = only the ones picked in
     * General settings. One country → checkout shows it locked.
     */
    function store_countries(): array
    {
        $all = (array) config('countries.list', []);

        if (setting('general.sell_to_mode', 'all') !== 'specific') {
            return $all;
        }

        $picked = array_intersect_key($all, array_flip((array) setting('general.sell_to_countries', [])));

        return $picked !== [] ? $picked : $all; // empty selection must never brick checkout
    }
}

if (! function_exists('image_dimensions')) {
    /**
     * Intrinsic [width, height] of an image on the public disk, or null.
     * Emitting these as width/height attributes lets the browser reserve
     * the box before the file loads — no layout shift (CLS). Handles
     * raster formats via getimagesize() and SVG via its attributes/viewBox.
     * Cached per path+mtime so uploads replacing a file refresh naturally.
     */
    function image_dimensions(?string $path): ?array
    {
        if (! $path) {
            return null;
        }

        $file = storage_path('app/public/'.ltrim($path, '/'));

        if (! is_file($file)) {
            return null;
        }

        return safe_cache('imgdim.'.md5($path.'|'.filemtime($file)), 86400 * 30, function () use ($file) {
            if (str_ends_with(strtolower($file), '.svg')) {
                $head = (string) file_get_contents($file, false, null, 0, 2048);
                if (preg_match('/<svg[^>]*\bwidth="(\d+(?:\.\d+)?)"[^>]*\bheight="(\d+(?:\.\d+)?)"/s', $head, $m)) {
                    return [(int) round((float) $m[1]), (int) round((float) $m[2])];
                }
                if (preg_match('/viewBox="[\d.\s-]*?([\d.]+)\s+([\d.]+)"\s*/s', $head, $m)) {
                    return [(int) round((float) $m[1]), (int) round((float) $m[2])];
                }

                return null;
            }

            $size = @getimagesize($file);

            return $size ? [(int) $size[0], (int) $size[1]] : null;
        });
    }
}

if (! function_exists('vite_fonts_links')) {
    /**
     * Font preloads + an EXTERNAL stylesheet link for the Vite-built fonts,
     * instead of Vite::fonts()'s inline <style> block (~4.7KB of @font-face
     * on every page hurts the HTML-to-text ratio). Falls back to the
     * framework helper during `npm run dev` (hot) or when no build exists.
     */
    function vite_fonts_links(): \Illuminate\Support\HtmlString
    {
        $manifestPath = public_path('build/fonts-manifest.json');

        if (is_file(public_path('hot')) || ! is_file($manifestPath)) {
            return new \Illuminate\Support\HtmlString(
                \Illuminate\Support\Facades\Vite::fonts()->toHtml()
            );
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];
        $html = '';

        // Preload ONLY the primary body weight (400) woff2, and only the first
        // such file — the above-the-fold text uses this one. Preloading every
        // weight/subset (6 files here) competes with the LCP image for
        // bandwidth and triggers PageSpeed "preloaded but not used" warnings
        // for the weights/subsets not painted early. The rest still load via
        // the font stylesheet linked right after, with font-display: swap.
        $bodyWeight = 400;
        foreach ((array) ($manifest['preloads'] ?? []) as $preload) {
            if (($preload['type'] ?? '') !== 'font/woff2' || empty($preload['file'])) {
                continue; // woff is the legacy fallback, never preloaded
            }
            if ((int) ($preload['weight'] ?? 0) !== $bodyWeight) {
                continue;
            }
            $html .= '<link rel="preload" as="font" type="font/woff2" crossorigin href="'.asset('build/'.$preload['file']).'">';
            break; // one primary-weight file is enough; extras load via the stylesheet
        }

        if (! empty($manifest['style']['file'])) {
            $html .= '<link rel="stylesheet" href="'.asset('build/'.$manifest['style']['file']).'">';
        }

        return new \Illuminate\Support\HtmlString($html);
    }
}

if (! function_exists('theme_css_href')) {
    /**
     * The admin theme overrides (Appearance settings) compiled to a static
     * CSS file on the public disk, so no inline <style> ships in the head.
     * Content-hashed filename = safe long caching; regenerates automatically
     * when the settings change. Returns null when nothing is overridden.
     */
    function theme_css_href(): ?string
    {
        $brand = setting('appearance.primary_color');
        $brandHover = setting('appearance.primary_hover_color');
        $saleBadge = setting('appearance.sale_badge_color');
        $radius = setting('appearance.border_radius');
        $cardShadow = setting('appearance.card_shadow', true);

        $radiusValue = match ($radius) {
            'none' => '0px',
            'sm' => '0.25rem',
            'lg' => '0.75rem',
            default => null,
        };

        $css = '';

        if ($brand) {
            $hover = $brandHover ?: $brand;
            $css .= ":root{--brand:{$brand};--brand-dark:{$hover};}"
                .".bg-indigo-600{background-color:{$brand} !important;}"
                .".text-indigo-600{color:{$brand} !important;}"
                .".hover\\:text-indigo-600:hover{color:{$brand} !important;}"
                .".border-indigo-600{border-color:{$brand} !important;}"
                .".ring-indigo-600{--tw-ring-color:{$brand} !important;}"
                .".focus\\:ring-indigo-500:focus,.focus\\:border-indigo-500:focus{--tw-ring-color:{$brand} !important;border-color:{$brand} !important;}"
                .".hover\\:bg-indigo-700:hover,.hover\\:bg-indigo-500:hover{background-color:{$hover} !important;}";
        }

        if ($saleBadge) {
            $css .= ".bg-red-600{background-color:{$saleBadge} !important;}";
        }

        if ($radiusValue !== null) {
            $css .= ".rounded,.rounded-md,.rounded-lg,.rounded-xl{border-radius:{$radiusValue} !important;}";
        }

        if (! $cardShadow) {
            $css .= 'article.group:hover{box-shadow:none !important;}';
        }

        if ($css === '') {
            return null;
        }

        $hash = substr(md5($css), 0, 12);
        $relative = "theme/overrides-{$hash}.css";
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        if (! $disk->exists($relative)) {
            $disk->put($relative, $css);
        }

        return asset('storage/'.$relative);
    }
}
