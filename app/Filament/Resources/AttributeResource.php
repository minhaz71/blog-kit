<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttributeResource\Pages\CreateAttribute;
use App\Filament\Resources\AttributeResource\Pages\EditAttribute;
use App\Filament\Resources\AttributeResource\Pages\ListAttributes;
use App\Models\Attribute;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class AttributeResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = Attribute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, ?string $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state ?? '')) : null),
                    TextInput::make('slug')->required()->unique(ignoreRecord: true),
                    Select::make('type')
                        ->options(['select' => 'Dropdown', 'color' => 'Color swatch', 'button' => 'Button pill'])
                        ->default('select')
                        ->native(false)
                        ->required(),
                ]),
                Repeater::make('values')
                    ->relationship()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('value')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug($state ?? ''))),
                            TextInput::make('slug')->required(),
                            TextInput::make('color_code')->placeholder('#000000'),
                        ]),
                    ])
                    ->columnSpanFull()
                    ->orderColumn('sort_order')
                    ->addActionLabel('Add value'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('values_count')->counts('values')->label('Values'),
                TextColumn::make('values_needing_review_count')
                    ->counts(['values as values_needing_review_count' => fn ($query) => $query->where('needs_review', true)])
                    ->label('Needs review')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'warning' : 'gray'),
            ])
            ->recordActions([\Filament\Actions\EditAction::make()])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributes::route('/'),
            'create' => CreateAttribute::route('/create'),
            'edit' => EditAttribute::route('/{record}/edit'),
        ];
    }
}
