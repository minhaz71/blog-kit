<?php

namespace App\Filament\Resources;

use App\Filament\Support\ResourceActions;
use App\Filament\Resources\HomepageSectionResource\Pages\CreateHomepageSection;
use App\Filament\Resources\HomepageSectionResource\Pages\EditHomepageSection;
use App\Filament\Resources\HomepageSectionResource\Pages\ListHomepageSections;
use App\Models\HomepageSection;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class HomepageSectionResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = HomepageSection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $label = 'Homepage section';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Full-width identity band: no more short card floating beside a
            // tall settings column — settings sections span the page below.
            Section::make('Section')->columnSpanFull()->columns(4)->schema([
                Select::make('type')
                    ->options(HomepageSection::TYPES)
                    ->required()
                    ->live()
                    ->native(false)
                    ->columnSpan(2),
                TextInput::make('sort_order')->numeric()->default(10),
                Toggle::make('is_active')->default(true)->inline(false),
                TextInput::make('title')->columnSpan(2),
                TextInput::make('subtitle')->columnSpan(2),
            ]),
            Section::make('Hero settings')->columnSpanFull()
                ->visible(fn (Get $get) => $get('type') === 'hero')
                ->columns(2)
                ->schema([
                    TextInput::make('settings.badge')
                        ->label('Badge text')
                        ->placeholder('1-hour delivery — Dubai, Sharjah & Ajman')
                        ->helperText('Small pill shown above the headline. Leave empty to hide.'),
                    TextInput::make('settings.overlay_opacity')->numeric()->helperText('Darkness over the image, 0-100.'),
                    TextInput::make('settings.button_text')->label('Primary button text'),
                    TextInput::make('settings.button_url')->label('Primary button link'),
                    TextInput::make('settings.button2_text')->label('Secondary button text'),
                    TextInput::make('settings.button2_url')->label('Secondary button link'),
                    FileUpload::make('settings.image')
                        ->label('Desktop banner')
                        ->image()->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                        ->disk('public')->directory('homepage')->imageEditor()
                        ->helperText('Wide crop, e.g. 1920×760.'),
                    FileUpload::make('settings.mobile_image')
                        ->label('Mobile banner')
                        ->image()->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                        ->disk('public')->directory('homepage')->imageEditor()
                        ->helperText('Tall crop, e.g. 900×1100 — shown on phones instead of the desktop banner.'),
                    Textarea::make('settings.description')->rows(3)->columnSpanFull(),
                ]),
            Section::make('USP strip')->columnSpanFull()
                ->visible(fn (Get $get) => $get('type') === 'usp_strip')
                ->schema([
                    Repeater::make('settings.items')
                        ->schema([
                            Grid::make(3)->schema([
                                Select::make('icon')
                                    ->options([
                                        'bolt' => 'Lightning (speed)',
                                        'truck' => 'Delivery truck',
                                        'clock' => 'Clock',
                                        'map-pin' => 'Map pin',
                                        'shield' => 'Shield (genuine/secure)',
                                        'banknotes' => 'Banknotes (payment)',
                                        'check' => 'Check circle',
                                    ])
                                    ->default('bolt')
                                    ->native(false),
                                TextInput::make('label')->required(),
                                TextInput::make('sub_label'),
                            ]),
                        ])
                        ->defaultItems(4)
                        ->addActionLabel('Add promise'),
                ]),
            Section::make('Banner settings')->columnSpanFull()
                ->visible(fn (Get $get) => $get('type') === 'banner')
                ->columns(2)
                ->schema([
                    FileUpload::make('settings.image')->image()->disk('public')->directory('homepage'),
                    TextInput::make('settings.link_url'),
                    Textarea::make('settings.description')->rows(2)->columnSpanFull(),
                ]),
            Section::make('Product list settings')->columnSpanFull()
                ->visible(fn (Get $get) => in_array($get('type'), ['featured_products', 'best_sellers', 'new_arrivals', 'on_sale']))
                ->columns(2)
                ->schema([
                    TextInput::make('settings.limit')->numeric()->default(8)->helperText('How many products to show.'),
                    Select::make('settings.category_id')
                        ->label('Limit to category')
                        ->options(fn () => \App\Models\Category::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->helperText('Optional — only show products from this category.'),
                ]),
            Section::make('Category catalogue settings')->columnSpanFull()
                ->visible(fn (Get $get) => $get('type') === 'category_catalogue')
                ->columns(3)
                ->schema([
                    \Filament\Forms\Components\Select::make('settings.categories')
                        ->label('Categories to show (in order)')
                        ->multiple()
                        ->options(fn () => \App\Models\Category::active()->orderBy('name')->pluck('name', 'slug'))
                        ->searchable()
                        ->columnSpanFull()
                        ->helperText('Pick and order the categories yourself. Shown up to rows × columns; leave empty to auto-fill with top categories.'),
                    \Filament\Forms\Components\Select::make('settings.columns')
                        ->label('Columns (desktop)')
                        ->options([2 => '2', 3 => '3', 4 => '4'])
                        ->default(4)
                        ->native(false),
                    \Filament\Forms\Components\Select::make('settings.rows')
                        ->label('Rows')
                        ->options([1 => '1', 2 => '2', 3 => '3'])
                        ->default(2)
                        ->native(false),
                    \Filament\Forms\Components\Toggle::make('settings.show_count')
                        ->label('Show product count badge')
                        ->default(true)
                        ->inline(false),
                ]),
            Section::make('Category grid settings')->columnSpanFull()
                ->visible(fn (Get $get) => $get('type') === 'category_grid')
                ->schema([
                    KeyValue::make('settings.category_slugs')
                        ->keyLabel('Category slug')
                        ->valueLabel('Custom title (optional)')
                        ->helperText('Category slugs — e.g. apparel, electronics.'),
                ]),
            Section::make('Testimonials')->columnSpanFull()
                ->visible(fn (Get $get) => $get('type') === 'testimonials')
                ->schema([
                    Repeater::make('settings.items')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('author')->required(),
                                TextInput::make('location'),
                            ]),
                            Textarea::make('quote')->required()->rows(3),
                        ])
                        ->addActionLabel('Add testimonial'),
                ]),
            Section::make('FAQ')->columnSpanFull()
                ->visible(fn (Get $get) => $get('type') === 'faq')
                ->schema([
                    Repeater::make('settings.items')
                        ->schema([
                            TextInput::make('question')->required(),
                            Textarea::make('answer')->required()->rows(3),
                        ])
                        ->addActionLabel('Add FAQ'),
                ]),
            Section::make('CTA')->columnSpanFull()
                ->visible(fn (Get $get) => $get('type') === 'cta')
                ->columns(2)
                ->schema([
                    TextInput::make('settings.button_text'),
                    TextInput::make('settings.button_url'),
                    Textarea::make('settings.description')->rows(3)->columnSpanFull(),
                ]),
            Section::make('Trust badges')->columnSpanFull()
                ->visible(fn (Get $get) => $get('type') === 'trust_badges')
                ->schema([
                    Repeater::make('settings.items')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('label')->required(),
                                TextInput::make('sub_label'),
                            ]),
                        ])
                        ->addActionLabel('Add badge'),
                ]),
            Section::make('Rich text')->columnSpanFull()
                ->visible(fn (Get $get) => $get('type') === 'text_block')
                ->schema([
                    RichEditor::make('settings.body')->columnSpanFull(),
                ]),
            Section::make('Newsletter')
                ->visible(fn (Get $get) => $get('type') === 'newsletter')
                ->columns(2)
                ->schema([
                    TextInput::make('settings.button_text')->default('Subscribe'),
                    Textarea::make('settings.description')->rows(2)->columnSpanFull(),
                ]),
            Section::make('Blog posts')
                ->visible(fn (Get $get) => $get('type') === 'blog_posts')
                ->schema([
                    TextInput::make('settings.limit')->numeric()->default(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable(),
                TextColumn::make('type')->badge()->formatStateUsing(fn (string $state) => HomepageSection::TYPES[$state] ?? $state),
                TextColumn::make('title')->searchable()->limit(40),
                TextColumn::make('subtitle')->limit(50)->toggleable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->options(HomepageSection::TYPES),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHomepageSections::route('/'),
            'create' => CreateHomepageSection::route('/create'),
            'edit' => EditHomepageSection::route('/{record}/edit'),
        ];
    }
}
