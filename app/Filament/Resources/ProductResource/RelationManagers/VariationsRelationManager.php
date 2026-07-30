<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariationsRelationManager extends RelationManager
{
    protected static string $relationship = 'variations';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sku')
                    ->label('SKU')
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('price')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                TextInput::make('sale_price')
                    ->numeric()
                    ->minValue(0)
                    ->lt('price'),
                TextInput::make('stock_qty')
                    ->numeric()
                    ->default(0)
                    // Only relevant when the PARENT product manages stock —
                    // mirrors the product form, which hides its own qty when
                    // "Manage stock" is off (then stock is driven by status).
                    ->visible(fn (): bool => (bool) $this->getOwnerRecord()?->manage_stock)
                    ->helperText('Units in stock for this variation.')
                    // Blank = 0 stock. The column is NOT NULL, and empty inputs
                    // arrive as null (ConvertEmptyStringsToNull), so coerce here.
                    ->dehydrateStateUsing(fn ($state) => (int) ($state ?? 0)),
                Select::make('stock_status')
                    ->options([
                        'in_stock' => 'In stock',
                        'out_of_stock' => 'Out of stock',
                        'on_backorder' => 'On backorder',
                    ])
                    ->default('in_stock')
                    ->native(false),
                Select::make('attributeValues')
                    ->label('Attribute values')
                    ->relationship('attributeValues', 'value')
                    ->multiple()
                    ->preload()
                    ->helperText('The attribute value combination this variation represents (e.g. Red + XL).'),
                FileUpload::make('image')
                    ->image()
                    ->disk('public')
                    ->directory('products/variations'),
                Toggle::make('is_active')
                    ->default(true),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->columns([
                ImageColumn::make('image')
                    ->disk('public')
                    ->square(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('attributeValues.value')
                    ->label('Attributes')
                    ->badge()
                    ->separator(','),
                TextColumn::make('price')
                    ->money(setting('general.currency', 'USD'))
                    ->sortable(),
                TextColumn::make('sale_price')
                    ->money(setting('general.currency', 'USD'))
                    ->placeholder('—'),
                TextColumn::make('stock_qty')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
