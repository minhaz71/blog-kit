<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductTemplateResource\Pages\CreateProductTemplate;
use App\Filament\Resources\ProductTemplateResource\Pages\EditProductTemplate;
use App\Filament\Resources\ProductTemplateResource\Pages\ListProductTemplates;
use App\Models\ProductTemplate;
use BackedEnum;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ProductTemplateResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = ProductTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 6;

    protected static ?string $label = 'Product template';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Template')
                ->icon(Heroicon::OutlinedRectangleGroup)
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->placeholder('Default single product'),
                    Toggle::make('is_default')
                        ->inline(false)
                        ->helperText('Apply to every product without its own template.'),
                ]),

            Section::make('Global settings')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->iconColor('gray')
                ->columns(2)
                ->schema([
                    Select::make('settings.container')
                        ->label('Content width')
                        ->options(['5xl' => 'Narrow', '6xl' => 'Medium', '7xl' => 'Wide (default)', 'full' => 'Full width'])
                        ->default('7xl')
                        ->native(false),
                    TextInput::make('settings.gallery_image_width')
                        ->label('Gallery image output width (px)')
                        ->numeric()
                        ->default(700)
                        ->helperText('Higher = sharper but heavier. 600–900 is ideal for product photos.'),
                    Fieldset::make('Structured data (schema.org)')
                        ->columns(3)
                        ->schema([
                            Toggle::make('settings.schema.product')->label('Product')->default(true),
                            Toggle::make('settings.schema.review')->label('Review / rating')->default(true)
                                ->helperText('Only real approved reviews are ever emitted.'),
                            Toggle::make('settings.schema.breadcrumb')->label('Breadcrumb')->default(true),
                            Toggle::make('settings.schema.faq')->label('FAQ')->default(true),
                            Toggle::make('settings.schema.organization')->label('Organization')->default(true),
                            Toggle::make('settings.schema.website')->label('Website')->default(true),
                            Toggle::make('settings.schema.localbusiness')->label('Local business')->default(true),
                        ]),
                ]),

            Section::make('Page layout')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->iconColor('primary')
                ->description('Drag to reorder. Each block chooses its column (left/right of the hero, or full width) and its own colours and font size. Add a Custom HTML block anywhere.')
                ->schema([
                    Builder::make('blocks')
                        ->hiddenLabel()
                        ->addActionLabel('Add block')
                        ->collapsible()
                        ->collapsed()
                        ->blockNumbers(false)
                        ->cloneable()
                        ->blocks(self::blocks())
                        ->default(ProductTemplate::defaultBlocks()),
                ]),
        ]);
    }

    /** @return array<int, Builder\Block> */
    protected static function blocks(): array
    {
        return [
            Builder\Block::make('breadcrumbs')->label('Breadcrumbs')->icon(Heroicon::OutlinedChevronRight)
                ->schema(self::style('full')),

            Builder\Block::make('gallery')->label('Product gallery')->icon(Heroicon::OutlinedPhoto)
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('image_width')->numeric()->label('Image width (px)')->placeholder('Template default'),
                        Toggle::make('show_thumbnails')->default(true)->inline(false),
                        Toggle::make('rounded')->label('Rounded corners')->default(true)->inline(false),
                    ]),
                    ...self::style('left'),
                ]),

            Builder\Block::make('title')->label('Product title')->icon(Heroicon::OutlinedHashtag)
                ->schema([
                    Toggle::make('show_brand')->label('Show brand above title')->default(true)->inline(false),
                    ...self::style('right'),
                ]),

            Builder\Block::make('rating')->label('Rating stars')->icon(Heroicon::OutlinedStar)
                ->schema(self::style('right')),

            Builder\Block::make('price')->label('Price')->icon(Heroicon::OutlinedBanknotes)
                ->schema(self::style('right', defaultFontSize: '2xl')),

            Builder\Block::make('key_facts')->label('Key facts (bullets)')->icon(Heroicon::OutlinedListBullet)
                ->schema([
                    TextInput::make('heading')->placeholder('Optional heading'),
                    Toggle::make('use_specifications')->label('Auto-fill from specifications')->default(true)->inline(false),
                    Repeater::make('items')
                        ->label('Extra facts')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('label')->required(),
                                TextInput::make('value'),
                            ]),
                        ])
                        ->addActionLabel('Add fact')
                        ->collapsed(),
                    ...self::style('right'),
                ]),

            Builder\Block::make('short_description')->label('Short description')->icon(Heroicon::OutlinedBars3BottomLeft)
                ->schema(self::style('right')),

            Builder\Block::make('variations')->label('Variation selectors')->icon(Heroicon::OutlinedSwatch)
                ->schema(self::style('right')),

            Builder\Block::make('add_to_cart')->label('Add to cart')->icon(Heroicon::OutlinedShoppingCart)
                ->schema([
                    TextInput::make('button_text')->default('Add to cart'),
                    Grid::make(2)->schema([
                        Toggle::make('show_quantity')->default(true)->inline(false),
                        Toggle::make('show_wishlist')->default(true)->inline(false),
                    ]),
                    ...self::style('right'),
                ]),

            Builder\Block::make('categories')->label('Category links')->icon(Heroicon::OutlinedTag)
                ->schema([
                    TextInput::make('label')->default('Categories'),
                    ...self::style('right'),
                ]),

            Builder\Block::make('payment')->label('Payment methods')->icon(Heroicon::OutlinedCreditCard)
                ->schema([
                    TextInput::make('heading')->default('Pay on delivery'),
                    TextInput::make('note')->label('Note under icons')
                        ->placeholder('We accept cash on delivery or card payment on delivery, anywhere in the UAE.'),
                    Select::make('methods')
                        ->multiple()
                        ->options([
                            // Pay-on-delivery options render as highlighted brand chips.
                            'cash' => 'Cash on Delivery ★',
                            'card' => 'Card on Delivery ★',
                            'paypal' => 'PayPal', 'visa' => 'VISA', 'mastercard' => 'Mastercard',
                            'amex' => 'Amex', 'applepay' => 'Apple Pay', 'gpay' => 'Google Pay',
                            'tabby' => 'tabby', 'tamara' => 'tamara',
                        ])
                        ->default(['cash', 'card', 'visa', 'mastercard', 'applepay', 'gpay'])
                        ->helperText('★ Cash/Card on Delivery show as filled brand chips with a checkmark; card networks show as neutral pills.'),
                    ...self::style('right'),
                ]),

            Builder\Block::make('delivery_info')->label('Delivery info boxes')->icon(Heroicon::OutlinedTruck)
                ->schema([
                    Repeater::make('boxes')
                        ->schema([
                            TextInput::make('title')->required(),
                            Textarea::make('body')->rows(2),
                            Grid::make(3)->schema([
                                TextInput::make('icon')->label('Icon (emoji)')->placeholder('🚚'),
                                ColorPicker::make('bg_color')->label('Background')->default('#0f766e'),
                                ColorPicker::make('text_color')->label('Text')->default('#ffffff'),
                            ]),
                        ])
                        ->addActionLabel('Add delivery box')
                        ->defaultItems(1)
                        ->collapsed(),
                    ...self::style('right'),
                ]),

            Builder\Block::make('description')->label('Long description / tabs')->icon(Heroicon::OutlinedDocumentText)
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('layout')->options(['tabs' => 'Tabbed (Description / Reviews)', 'plain' => 'Plain heading'])->default('tabs')->native(false),
                        TextInput::make('heading')->default('Description'),
                    ]),
                    Toggle::make('show_reviews_tab')->label('Show Reviews tab')->default(true)->inline(false),
                    ...self::style('full'),
                ]),

            Builder\Block::make('specifications')->label('Specifications table')->icon(Heroicon::OutlinedTableCells)
                ->schema([
                    TextInput::make('heading')->default('Specifications'),
                    ...self::style('full'),
                ]),

            Builder\Block::make('faq')->label('FAQ accordion')->icon(Heroicon::OutlinedQuestionMarkCircle)
                ->schema(self::style('full')),

            Builder\Block::make('reviews')->label('Reviews')->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->schema([
                    TextInput::make('heading')->default('Reviews'),
                    Toggle::make('show_form')->label('Show review form')->default(true)->inline(false),
                    ...self::style('full'),
                ]),

            Builder\Block::make('related')->label('Related products')->icon(Heroicon::OutlinedSquares2x2)
                ->schema([
                    TextInput::make('heading')->default('Related products'),
                    TextInput::make('limit')->numeric()->default(4),
                    ...self::style('full'),
                ]),

            Builder\Block::make('cross_sells')->label('Frequently bought together')->icon(Heroicon::OutlinedSquaresPlus)
                ->schema([
                    TextInput::make('heading')->default('Frequently bought together'),
                    ...self::style('full'),
                ]),

            Builder\Block::make('upsells')->label('Upsells')->icon(Heroicon::OutlinedArrowTrendingUp)
                ->schema([
                    TextInput::make('heading')->default('You may prefer'),
                    ...self::style('right'),
                ]),

            Builder\Block::make('heading')->label('Custom heading')->icon(Heroicon::OutlinedHashtag)
                ->schema([
                    TextInput::make('text')->required(),
                    Select::make('level')->options(['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4'])->default('h2')->native(false),
                    ...self::style('full'),
                ]),

            Builder\Block::make('html')->label('Custom HTML block')->icon(Heroicon::OutlinedCodeBracket)
                ->schema([
                    RichEditor::make('content')->hiddenLabel()->helperText('Free text / HTML. Supports {{block:key}} shortcodes.'),
                    ...self::style('full'),
                ]),

            Builder\Block::make('divider')->label('Divider')->icon(Heroicon::OutlinedMinus)
                ->schema(self::style('full')),

            Builder\Block::make('spacer')->label('Spacer')->icon(Heroicon::OutlinedArrowsUpDown)
                ->schema([
                    TextInput::make('height')->numeric()->default(24)->label('Height (px)'),
                ]),
        ];
    }

    /**
     * Shared per-block style controls (placement, colours, font size). The
     * default column differs by block so the seeded layout lands correctly.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    protected static function style(string $defaultColumn = 'full', ?string $defaultFontSize = null): array
    {
        return [
            Fieldset::make('Style')
                ->columns(3)
                ->schema([
                    Select::make('column')
                        ->label('Placement')
                        ->options(['left' => 'Hero — left', 'right' => 'Hero — right', 'full' => 'Full width'])
                        ->default($defaultColumn)
                        ->native(false),
                    Select::make('font_size')
                        ->options(ProductTemplate::FONT_SIZES)
                        ->default($defaultFontSize)
                        ->placeholder('Default')
                        ->native(false),
                    Select::make('align')
                        ->options(['left' => 'Left', 'center' => 'Center', 'right' => 'Right'])
                        ->placeholder('Default')
                        ->native(false),
                    ColorPicker::make('text_color')->label('Text colour'),
                    ColorPicker::make('heading_color')->label('Heading colour'),
                    ColorPicker::make('bg_color')->label('Background'),
                    Select::make('padding')
                        ->options(['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'])
                        ->placeholder('None')
                        ->native(false),
                    TextInput::make('custom_class')->label('CSS class')->placeholder('optional'),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                IconColumn::make('is_default')->boolean()->label('Default'),
                TextColumn::make('blocks')
                    ->label('Blocks')
                    ->state(fn (ProductTemplate $record) => count($record->blocks ?? [])),
                TextColumn::make('products_count')->counts('products')->label('Products using')->badge(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\ReplicateAction::make()->excludeAttributes(['is_default']),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('is_default', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductTemplates::route('/'),
            'create' => CreateProductTemplate::route('/create'),
            'edit' => EditProductTemplate::route('/{record}/edit'),
        ];
    }
}
