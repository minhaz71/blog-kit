<?php

namespace App\Filament\Resources\ProductTemplateResource\Pages;

use App\Filament\Resources\ProductTemplateResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductTemplate extends EditRecord
{
    protected static string $resource = ProductTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview a product')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn () => optional(
                    \App\Models\Product::where('product_template_id', $this->record->id)->first()
                        ?? \App\Models\Product::query()->latest()->first()
                )->url() ?: null, shouldOpenInNewTab: true)
                ->visible(fn () => \App\Models\Product::query()->exists()),
            DeleteAction::make(),
        ];
    }
}
