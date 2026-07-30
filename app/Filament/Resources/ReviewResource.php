<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages\EditReview;
use App\Filament\Resources\ReviewResource\Pages\ListReviews;
use App\Models\Review;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ReviewResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Select::make('status')
                    ->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])
                    ->default('pending')
                    ->required()
                    ->native(false),
                TextInput::make('rating')->numeric()->minValue(1)->maxValue(5)->required(),
                TextInput::make('title'),
                TextInput::make('reviewer_name')->required(),
            ]),
            Textarea::make('body')->rows(4)->required()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')->label('Product')->limit(30)->toggleable(),
                TextColumn::make('reviewer_name')->searchable(),
                TextColumn::make('rating')->sortable(),
                TextColumn::make('title')->limit(40),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
                SelectFilter::make('rating')->options([1 => '1★', 2 => '2★', 3 => '3★', 4 => '4★', 5 => '5★']),
            ])
            ->recordActions([\Filament\Actions\EditAction::make()])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('bulkApprove')
                        ->label('Approve')
                        ->icon(\Filament\Support\Icons\Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->action(fn (\Illuminate\Support\Collection $records) => $records->each->update(['status' => 'approved']))
                        ->deselectRecordsAfterCompletion(),
                    \Filament\Actions\BulkAction::make('bulkReject')
                        ->label('Reject')
                        ->icon(\Filament\Support\Icons\Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->action(fn (\Illuminate\Support\Collection $records) => $records->each->update(['status' => 'rejected']))
                        ->deselectRecordsAfterCompletion(),
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
            'edit' => EditReview::route('/{record}/edit'),
        ];
    }
}
