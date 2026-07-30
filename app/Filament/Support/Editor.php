<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;

/**
 * Shared "classic editor" building blocks: a rich editor with an
 * HTML-source modal + exact preview, and a Custom code tab (HTML / CSS /
 * CSS file / JS) with its own combined preview.
 */
class Editor
{
    /** Rich editor with WordPress-style Text mode and an exact preview. */
    public static function rich(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->hintActions([
                Action::make("{$name}_source")
                    ->label('HTML source')
                    ->icon(Heroicon::OutlinedCodeBracket)
                    ->color('gray')
                    ->modalHeading('Edit HTML source')
                    ->modalDescription('Inline style="" attributes are preserved. <style> blocks are automatically moved to this page\'s Custom CSS on save.')
                    ->modalSubmitActionLabel('Apply')
                    ->modalWidth('4xl')
                    ->fillForm(fn (RichEditor $component): array => [
                        'source' => (string) ($component->getState() ?? ''),
                    ])
                    ->schema([
                        Textarea::make('source')
                            ->hiddenLabel()
                            ->rows(22)
                            ->extraInputAttributes([
                                'style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px;',
                                'spellcheck' => 'false',
                            ]),
                    ])
                    ->action(function (array $data, RichEditor $component): void {
                        $component->state($data['source'] ?? '');
                    }),
                Action::make("{$name}_preview")
                    ->label('Preview')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading('Preview')
                    ->modalDescription('Content rendered with the site stylesheet plus this page\'s custom CSS — as it will appear on the frontend.')
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (RichEditor $component, Get $get) => view('filament.editor-preview', [
                        'html' => self::buildPreviewHtml(
                            body: '<div class="prose max-w-none">'.($component->getState() ?? '').'</div>',
                            css: (string) ($get('custom_css') ?? ''),
                            cssFile: self::resolveCssFileUrl($get('custom_css_file')),
                        ),
                    ])),
            ]);
    }

    /**
     * "Custom code" tab for models with custom_html / custom_css /
     * custom_css_file / custom_js columns (products, categories, posts, pages).
     */
    public static function customCodeTab(): Tab
    {
        $mono = [
            'style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px;',
            'spellcheck' => 'false',
        ];

        return Tab::make('Custom code')
            // Raw HTML/JS here renders unescaped site-wide — a stored-XSS
            // surface. Restrict the whole tab to Super Admins so ordinary
            // content staff cannot inject scripts. Existing values still
            // render; only editing is locked down.
            ->visible(fn () => auth()->user()?->hasRole('Super Admin') ?? false)
            ->schema([
                Textarea::make('custom_html')
                    ->label('Custom HTML')
                    ->rows(8)
                    ->extraInputAttributes($mono)
                    ->helperText('Rendered on the page below the main content. Full HTML with inline CSS allowed.')
                    ->hintAction(
                        Action::make('custom_code_preview')
                            ->label('Preview HTML + CSS')
                            ->icon(Heroicon::OutlinedEye)
                            ->color('gray')
                            ->modalHeading('Custom code preview')
                            ->modalDescription('Custom HTML rendered with the custom CSS below and the site stylesheet.')
                            ->modalWidth('5xl')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Close')
                            ->modalContent(fn (Get $get) => view('filament.editor-preview', [
                                'html' => self::buildPreviewHtml(
                                    body: '<div class="custom-html-block">'.($get('custom_html') ?? '').'</div>',
                                    css: (string) ($get('custom_css') ?? ''),
                                    cssFile: self::resolveCssFileUrl($get('custom_css_file')),
                                ),
                            ])),
                    ),
                Textarea::make('custom_css')
                    ->label('Custom CSS')
                    ->rows(8)
                    ->extraInputAttributes($mono)
                    ->helperText('Served as a dedicated cacheable .css file, loaded only on this page. Do not include <style> tags.'),
                FileUpload::make('custom_css_file')
                    ->label('Extra CSS file')
                    ->disk('public')
                    ->directory('custom-css')
                    ->acceptedFileTypes(['text/css', 'text/plain'])
                    ->maxSize(512)
                    ->helperText('Optional stylesheet uploaded as a file — loaded via <link> only on this page.'),
                Textarea::make('custom_js')
                    ->label('Custom JavaScript')
                    ->rows(8)
                    ->extraInputAttributes($mono)
                    ->helperText('Injected before </body> on this page only. Do not include <script> tags.'),
            ]);
    }

    /** Full standalone document for the preview iframe. */
    public static function buildPreviewHtml(string $body, string $css = '', ?string $cssFile = null): string
    {
        $siteCss = '';

        try {
            $siteCss = '<link rel="stylesheet" href="'.Vite::asset('resources/css/app.css').'">';
        } catch (\Throwable) {
            // No built assets (e.g. dev without a running Vite server) — preview without the site stylesheet.
        }

        $fileLink = $cssFile ? '<link rel="stylesheet" href="'.e($cssFile).'">' : '';
        $inline = $css !== '' ? '<style>'.$css.'</style>' : '';

        return '<!doctype html><html><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .$siteCss.$fileLink.$inline
            .'</head><body style="padding:24px;background:#fff">'
            .$body
            .'</body></html>';
    }

    /** Normalize a FileUpload state (array of temp files or stored path) to a URL, if stored. */
    public static function resolveCssFileUrl(mixed $state): ?string
    {
        if (is_array($state)) {
            $state = collect($state)->first(fn ($v) => is_string($v));
        }

        if (! is_string($state) || $state === '') {
            return null;
        }

        return Str::startsWith($state, ['http://', 'https://', '/'])
            ? $state
            : Storage::disk('public')->url($state);
    }
}
