<?php

namespace App\Filament\Pages;

use App\Models\ContentReplaceBatch;
use App\Services\Content\FindReplaceService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Site-wide Find & Replace across content/SEO prose fields. Dry run previews
 * every match (table + record + snippet) and writes nothing; Replace applies
 * inside a transaction with a full snapshot for one-click Undo. Never touches
 * names, prices, slugs — only the whitelisted prose columns in FindReplaceService.
 *
 * @property-read \Filament\Schemas\Schema $form
 */
class FindReplace extends Page
{
    use \App\Filament\Concerns\GatedByPermission;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlassCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Find & Replace';

    protected string $view = 'filament.pages.find-replace';

    public string $find = '';

    public string $replace = '';

    public bool $caseSensitive = true;

    public bool $wholeWord = false;

    /** @var array<string,bool> scope key => selected */
    public array $scopes = [];

    /** Dry-run result (null until previewed). */
    public ?array $preview = null;

    public function mount(): void
    {
        // Default-select the recommended scopes (core content + SEO).
        foreach (app(FindReplaceService::class)->defaultScopeKeys() as $key) {
            $this->scopes[$key] = true;
        }
    }

    public function scopeOptions(): array
    {
        return app(FindReplaceService::class)->scopeOptions();
    }

    protected function selectedScopeKeys(): array
    {
        return array_keys(array_filter($this->scopes));
    }

    protected function opts(): array
    {
        return ['case_sensitive' => $this->caseSensitive, 'whole_word' => $this->wholeWord];
    }

    public function dryRun(): void
    {
        $this->preview = null;

        if (trim($this->find) === '') {
            Notification::make()->title('Enter the text to find first.')->warning()->send();

            return;
        }
        if ($this->selectedScopeKeys() === []) {
            Notification::make()->title('Choose at least one place to search.')->warning()->send();

            return;
        }

        $this->preview = app(FindReplaceService::class)->dryRun($this->find, $this->selectedScopeKeys(), $this->opts());

        Notification::make()
            ->title("{$this->preview['occurrences']} match(es) in {$this->preview['records']} record(s).")
            ->info()
            ->send();
    }

    public function apply(): void
    {
        if (trim($this->find) === '' || $this->selectedScopeKeys() === []) {
            Notification::make()->title('Run a dry run first.')->warning()->send();

            return;
        }

        $batch = app(FindReplaceService::class)->apply(
            $this->find,
            $this->replace,
            $this->selectedScopeKeys(),
            $this->opts(),
            auth()->id(),
        );

        $this->preview = null;
        $this->find = '';
        $this->replace = '';

        Notification::make()
            ->title('Replaced')
            ->body("Updated {$batch->occurrences_count} occurrence(s) across {$batch->records_count} record(s). You can undo this from the history below.")
            ->success()
            ->send();
    }

    public function undo(int $batchId): void
    {
        $batch = ContentReplaceBatch::find($batchId);

        if (! $batch || $batch->isReverted()) {
            return;
        }

        $result = app(FindReplaceService::class)->revert($batch);

        Notification::make()
            ->title('Reverted')
            ->body("Restored {$result['restored']} field(s)."
                .($result['skipped'] ? " Skipped {$result['skipped']} field(s) edited since." : ''))
            ->success()
            ->send();
    }

    public function recentBatches()
    {
        return ContentReplaceBatch::latest()->limit(15)->get();
    }
}
