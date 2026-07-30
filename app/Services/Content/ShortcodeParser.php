<?php

namespace App\Services\Content;

use App\Models\ContentBlock;
use Illuminate\Support\Facades\Cache;

/**
 * Replaces `{{block:key}}` shortcodes in HTML with the rendered content of the
 * matching ContentBlock. Unknown blocks fall back to the original tag so they
 * are visible for authors to fix.
 */
class ShortcodeParser
{
    /** Shortcodes rendered live from Blade partials (never cached — they carry CSRF tokens / settings). */
    protected const VIEW_SHORTCODES = [
        'contact_form' => 'partials.contact-form',
        'contact_info' => 'partials.contact-info',
    ];

    public function parse(?string $html): string
    {
        if (! $html) {
            return '';
        }

        // {{contact_form}} / {{contact_info}} → live partials, usable in any
        // CMS page, product description or content block.
        $html = preg_replace_callback(
            '/\{\{\s*('.implode('|', array_keys(self::VIEW_SHORTCODES)).')\s*\}\}/i',
            fn ($m) => view(self::VIEW_SHORTCODES[strtolower($m[1])])->render(),
            $html,
        );

        $blocks = $this->activeBlocksByKey();
        if ($blocks === []) {
            return $html;
        }

        return preg_replace_callback(
            '/\{\{\s*block:([a-z0-9_-]+)\s*\}\}/i',
            function ($match) use ($blocks) {
                $key = strtolower($match[1]);

                return isset($blocks[$key]) ? $blocks[$key] : $match[0];
            },
            $html,
        );
    }

    /** @return array<string, string>  keyed by block key, values are rendered HTML */
    protected function activeBlocksByKey(): array
    {
        return Cache::remember('content_blocks.rendered', 300, function () {
            $out = [];
            foreach (ContentBlock::query()->where('is_active', true)->get() as $block) {
                $out[strtolower($block->key)] = $block->render();
            }

            return $out;
        });
    }
}
