<?php

namespace App\Filament\Resources\KeywordResearchResource\Pages;

use App\Filament\Resources\KeywordResearchResource;
use App\Jobs\RunKeywordResearchJob;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateKeywordResearch extends CreateRecord
{
    protected static string $resource = KeywordResearchResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['status'] = 'queued';
        // "This site" (empty) → null; a chosen spoke keeps its id.
        $data['site_id'] = ($data['site_id'] ?? '') !== '' ? (int) $data['site_id'] : null;

        return $data;
    }

    protected function afterCreate(): void
    {
        // Detached process (no web-request timeout); queue fallback if it can't spawn.
        if (! \App\Support\BackgroundProcess::artisan(['keyword:research', (string) $this->record->id])) {
            RunKeywordResearchJob::dispatch($this->record->id);
        }

        Notification::make()
            ->title('Research started')
            ->body('Discovering and clustering keywords… refresh in a moment. If nothing appears (no queue worker), use "Run research" here.')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
