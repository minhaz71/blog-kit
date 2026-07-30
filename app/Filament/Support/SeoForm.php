<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Reusable SEO meta form (bound to the polymorphic seoMeta relation)
 * shared by products, categories, pages and posts.
 */
class SeoForm
{
    /** @return array<\Filament\Schemas\Components\Component> */
    public static function components(): array
    {
        return [
            Placeholder::make('seo_score_badge')
                ->label('SEO score')
                ->content(function (?Model $record): HtmlString {
                    $score = $record?->seoMeta?->seo_score;

                    if ($score === null) {
                        return new HtmlString('<span style="color:#9ca3af">Not analysed yet</span>');
                    }

                    $color = $score >= 80 ? '#16a34a' : ($score >= 50 ? '#d97706' : '#dc2626');

                    return new HtmlString(
                        "<span style=\"display:inline-block;padding:2px 10px;border-radius:9999px;font-weight:600;color:#fff;background:{$color}\">{$score} / 100</span>"
                    );
                }),
            Group::make([
                TextInput::make('title')
                    ->label('Meta title')
                    ->maxLength(255)
                    ->live(debounce: 300)
                    ->helperText(fn (?string $state): HtmlString => self::charCounter($state, 60)),
                Textarea::make('description')
                    ->label('Meta description')
                    ->rows(3)
                    ->maxLength(500)
                    ->live(debounce: 300)
                    ->helperText(fn (?string $state): HtmlString => self::charCounter($state, 160)),
                TextInput::make('focus_keyword')
                    ->maxLength(255),
                TextInput::make('canonical_url')
                    ->url()
                    ->maxLength(255),
                Group::make([
                    Toggle::make('noindex')
                        ->helperText('Ask search engines not to index this page.'),
                    Toggle::make('nofollow')
                        ->helperText('Ask search engines not to follow links on this page.'),
                    Toggle::make('schema_enabled')
                        ->label('Structured data (schema.org)')
                        ->default(true),
                ])->columns(3),
                Section::make('Social sharing (Open Graph)')
                    ->collapsed()
                    ->schema([
                        TextInput::make('og_title')
                            ->label('OG title')
                            ->maxLength(255),
                        Textarea::make('og_description')
                            ->label('OG description')
                            ->rows(2)
                            ->maxLength(500),
                        FileUpload::make('og_image')
                            ->label('OG image')
                            ->image()
                            ->disk('public')
                            ->directory('seo'),
                        TextInput::make('twitter_title')
                            ->maxLength(255),
                        Textarea::make('twitter_description')
                            ->rows(2)
                            ->maxLength(500),
                        FileUpload::make('twitter_image')
                            ->image()
                            ->disk('public')
                            ->directory('seo'),
                    ])
                    ->columns(2),
            ])->relationship('seoMeta'),
        ];
    }

    /** Live character counter: green within limit, amber near it, red over. */
    public static function charCounter(?string $state, int $recommended): HtmlString
    {
        $length = mb_strlen($state ?? '');
        $color = $length === 0 ? '#9ca3af'
            : ($length <= $recommended ? '#16a34a'
            : ($length <= (int) ($recommended * 1.15) ? '#d97706' : '#dc2626'));

        return new HtmlString(
            "<span style=\"color:{$color};font-variant-numeric:tabular-nums\">{$length} / {$recommended} recommended characters</span>"
        );
    }

    /** Badge column showing the related seo score in tables. */
    public static function scoreColumn(): TextColumn
    {
        return TextColumn::make('seoMeta.seo_score')
            ->label('SEO')
            ->badge()
            ->placeholder('—')
            ->color(fn ($state): string => match (true) {
                $state === null => 'gray',
                $state >= 80 => 'success',
                $state >= 50 => 'warning',
                default => 'danger',
            })
            ->sortable();
    }
}
