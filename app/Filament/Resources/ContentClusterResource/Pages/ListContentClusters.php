<?php

namespace App\Filament\Resources\ContentClusterResource\Pages;

use App\Filament\Resources\ContentClusterResource;
use App\Services\Ai\CategoryPlanner;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListContentClusters extends ListRecords
{
    protected static string $resource = ContentClusterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buildCategories')
                ->label('Build category tree')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedRectangleGroup)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Build the blog category tree')
                ->modalDescription('Groups these clusters into mother categories and files each cluster as a sub-category (capped, idempotent). Existing categories are reused, never duplicated.')
                ->action(function (): void {
                    $result = app(CategoryPlanner::class)->run();

                    Notification::make()
                        ->title($result['message'])
                        ->success()
                        ->send();
                }),
        ];
    }
}
