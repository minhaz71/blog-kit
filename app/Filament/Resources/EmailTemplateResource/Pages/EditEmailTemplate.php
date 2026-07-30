<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview email')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => route('admin.email-templates.preview', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}
