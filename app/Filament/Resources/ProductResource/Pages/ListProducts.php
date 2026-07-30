<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Console\Commands\FillProductImagesFromDrive;
use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Support\BackgroundProcess;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function missingImageCount(): int
    {
        return Product::query()
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('featured_image')->orWhere('featured_image', ''))
            ->whereDoesntHave('images')
            ->count();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fillMissingImages')
                ->label(fn (): string => 'Fill missing images from Drive ('.$this->missingImageCount().')')
                ->icon(Heroicon::OutlinedPhoto)
                ->color('gray')
                ->visible(fn (): bool => $this->missingImageCount() > 0)
                ->modalHeading('Fill every product missing an image')
                ->modalDescription('Downloads the best-matching photo (by file name) from your Drive folder and its subfolders for all products that currently have no image, and attaches it with alt text. No AI — nothing is charged.')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('folder')
                        ->label('Google Drive folder (link or ID)')
                        ->placeholder('https://drive.google.com/drive/folders/…')
                        ->default(fn () => (string) setting('catalog.drive_image_folder'))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    if ((string) setting('ai.google_drive_api_key') === '') {
                        Notification::make()
                            ->title('No Google Drive API key')
                            ->body('Add one under Settings → AI settings first.')
                            ->danger()->send();

                        return;
                    }

                    FillProductImagesFromDrive::clearStatus();

                    $launched = BackgroundProcess::artisan([
                        'products:fill-images',
                        '--folder='.$data['folder'],
                        '--user='.(string) auth()->id(),
                    ]);

                    Notification::make()
                        ->title($launched ? 'Filling missing images in the background' : 'Could not start the image fetcher')
                        ->body($launched ? 'Use “Drive image status” to watch progress.' : 'No background worker available here.')
                        ->{$launched ? 'success' : 'warning'}()->send();
                }),
            Action::make('driveImageStatus')
                ->label('Drive image status')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (): bool => FillProductImagesFromDrive::status() !== null)
                ->action(function (): void {
                    $status = FillProductImagesFromDrive::status();

                    if (! $status) {
                        Notification::make()->title('No recent Drive image run')->info()->send();

                        return;
                    }

                    $type = match ($status['state']) {
                        'done' => 'success',
                        'failed' => 'danger',
                        default => 'info',
                    };

                    Notification::make()
                        ->title(match ($status['state']) {
                            'done' => 'Drive image fill complete',
                            'failed' => 'Drive image fill failed',
                            default => 'Drive image fill running…',
                        })
                        ->body($status['message'] ?? '')
                        ->{$type}()->send();

                    if ($status['state'] === 'done') {
                        FillProductImagesFromDrive::clearStatus();
                    }
                }),
            CreateAction::make(),
        ];
    }

    /** WordPress-style status tabs, including a visible Trash bin with a count. */
    public function getTabs(): array
    {
        // The resource query drops the soft-delete scope (so trashed rows are
        // resolvable for restore/force-delete), so each non-trash tab must
        // exclude trashed rows explicitly; the Trash tab shows only them.
        return [
            'all' => Tab::make('All')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('deleted_at'))
                ->badge(Product::count()),
            'published' => Tab::make('Published')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'published')->whereNull('deleted_at'))
                ->badge(Product::where('status', 'published')->count()),
            'draft' => Tab::make('Draft')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft')->whereNull('deleted_at'))
                ->badge(Product::where('status', 'draft')->count()),
            'trash' => Tab::make('Trash')
                ->icon('heroicon-o-trash')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('deleted_at'))
                ->badge(Product::onlyTrashed()->count())
                ->badgeColor('danger'),
        ];
    }
}
