<?php

namespace App\Filament\Resources;

use App\Models\Category;
use App\Models\CustomSchema;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Unlimited extra JSON-LD blocks: global (every page) or attached to one
 * product/category/post/page. Injected into the page's schema @graph next
 * to the auto-generated Product/FAQ/Breadcrumb schemas.
 */
class CustomSchemaResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = CustomSchema::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracket;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'custom schema';

    /** [ui key => morph class] */
    public const SCOPES = [
        'product' => Product::class,
        'category' => Category::class,
        'post' => Post::class,
        'page' => Page::class,
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('For your reference only, e.g. "Warranty schema", "Delivery HowTo".'),
                Toggle::make('is_active')->default(true),
                Select::make('schemable_type')
                    ->label('Applies to')
                    ->options([
                        '' => 'Every page (global)',
                        Product::class => 'One product',
                        Category::class => 'One category',
                        Post::class => 'One blog post',
                        Page::class => 'One page',
                    ])
                    ->live()
                    ->native(false),
                Select::make('schemable_id')
                    ->label('Which one')
                    ->options(function (Get $get) {
                        return match ($get('schemable_type')) {
                            Product::class => Product::query()->orderBy('name')->limit(500)->pluck('name', 'id'),
                            Category::class => Category::query()->orderBy('name')->pluck('name', 'id'),
                            Post::class => Post::query()->orderBy('title')->limit(500)->pluck('title', 'id'),
                            Page::class => Page::query()->orderBy('title')->pluck('title', 'id'),
                            default => [],
                        };
                    })
                    ->searchable()
                    ->visible(fn (Get $get) => filled($get('schemable_type')))
                    ->required(fn (Get $get) => filled($get('schemable_type'))),
                Textarea::make('json_ld')
                    ->label('JSON-LD')
                    ->rows(14)
                    ->required()
                    ->columnSpanFull()
                    ->helperText('One schema.org object, e.g. {"@type": "HowTo", "name": "…"}. The @context is added automatically. Validate with Google after saving (action on the list).')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $state)
                    ->dehydrateStateUsing(fn ($state) => json_decode((string) $state, true))
                    ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                        $decoded = json_decode((string) $value, true);

                        if (! is_array($decoded)) {
                            $fail('Not valid JSON: '.json_last_error_msg());
                        } elseif (empty($decoded['@type'])) {
                            $fail('The schema object must declare an "@type" (e.g. "HowTo", "VideoObject").');
                        }
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('json_ld->@type')->label('Type')->badge(),
                TextColumn::make('schemable_type')
                    ->label('Scope')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : 'Global')
                    ->badge()
                    ->color(fn (?string $state) => $state ? 'info' : 'success'),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('validate')
                    ->label('Test with Google')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->url(function (CustomSchema $record): string {
                        $target = $record->schemable;
                        $url = method_exists($target, 'url') ? $target?->url() : url('/');

                        return 'https://search.google.com/test/rich-results?url='.urlencode($url ?: url('/'));
                    }, shouldOpenInNewTab: true),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => CustomSchemaResource\Pages\ListCustomSchemas::route('/'),
            'create' => CustomSchemaResource\Pages\CreateCustomSchema::route('/create'),
            'edit' => CustomSchemaResource\Pages\EditCustomSchema::route('/{record}/edit'),
        ];
    }
}
