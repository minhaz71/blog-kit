<?php

namespace App\Models\Concerns;

/**
 * Content pasted into the editor may carry <style> blocks. On save, those
 * blocks are extracted out of the HTML and appended to the entity's
 * custom_css — which is served as this page's own cacheable stylesheet —
 * so the editor never mangles them and the CSS stays scoped to the page.
 */
trait MovesInlineStylesToCustomCss
{
    public static function bootMovesInlineStylesToCustomCss(): void
    {
        static::saving(function ($model): void {
            foreach ($model->styleExtractionColumns() as $column) {
                $html = $model->{$column};

                if (! is_string($html) || stripos($html, '<style') === false) {
                    continue;
                }

                preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $matches);

                $css = trim(implode("\n\n", array_map('trim', $matches[1])));

                $model->{$column} = trim((string) preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html));

                if ($css === '') {
                    continue;
                }

                $existing = trim((string) ($model->custom_css ?? ''));

                // Skip blocks already moved on a previous save.
                if ($existing !== '' && str_contains($existing, $css)) {
                    continue;
                }

                $model->custom_css = trim(
                    ($existing !== '' ? $existing."\n\n" : '')
                    ."/* Moved from {$column} editor */\n".$css
                );
            }
        });
    }

    /** Columns whose <style> blocks should be moved to custom_css. */
    protected function styleExtractionColumns(): array
    {
        return ['content'];
    }
}
