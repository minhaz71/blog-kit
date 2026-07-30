<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Gallery images';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('path')
                    ->label('Image')
                    ->image()
                    ->disk('public')
                    ->directory('products')
                    // The permalink comes from the ORIGINAL file name,
                    // slugified once at upload: "terea kazakhstan amber.jpg"
                    // → terea-kazakhstan-amber.jpg. Fixed from then on.
                    ->getUploadedFileNameForStorageUsing(function (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file): string {
                        $slug = \App\Services\Seo\ImageSeoRules::slugFromOriginalName(
                            $file->getClientOriginalName(),
                            $this->getOwnerRecord()->slug ?: 'product',
                        );
                        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');

                        return basename(\App\Services\Seo\ImageSeoRules::uniquePath('products', $slug, $extension));
                    })
                    ->required()
                    ->columnSpanFull()
                    ->helperText('Name the file properly BEFORE uploading — the URL slug comes from the file name (e.g. "terea kazakhstan amber.jpg" becomes /terea-kazakhstan-amber.jpg) and is fixed once uploaded.'),
                TextInput::make('alt')
                    ->label('Alt text')
                    ->maxLength(\App\Services\Seo\ImageSeoRules::ALT_MAX)
                    ->placeholder(fn () => $this->getOwnerRecord()->name)
                    ->helperText('Describe what the image shows: product + attribute + view. ≤'.\App\Services\Seo\ImageSeoRules::ALT_MAX.' chars. Falls back to the product name.'),
                TextInput::make('title')
                    ->label('Title (hover tooltip)')
                    ->maxLength(\App\Services\Seo\ImageSeoRules::TITLE_MAX)
                    ->helperText('Short hint shown on hover. Falls back to the alt text.'),
                TextInput::make('caption')
                    ->label('Caption')
                    ->maxLength(\App\Services\Seo\ImageSeoRules::CAPTION_MAX)
                    ->helperText('Optional buyer-facing line shown with the image.'),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('alt')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('path')
                    ->label('Image')
                    ->disk('public')
                    ->square(),
                TextColumn::make('alt')
                    ->label('Alt text')
                    ->placeholder('— missing —')
                    ->limit(40),
                TextColumn::make('title')
                    ->label('Title')
                    ->placeholder('—')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('path')
                    ->label('Filename')
                    ->formatStateUsing(fn (string $state) => basename($state))
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('sort_order')
                    ->sortable(),
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
