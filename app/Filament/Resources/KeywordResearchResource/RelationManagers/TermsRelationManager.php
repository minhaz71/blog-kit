<?php

namespace App\Filament\Resources\KeywordResearchResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The discovered keyword universe for a research run: volume/difficulty/intent/
 * cluster/role/funnel plus the "chosen" toggle that controls which terms become
 * blog ideas. Uncheck the noise, then "Create content plan".
 */
class TermsRelationManager extends RelationManager
{
    protected static string $relationship = 'terms';

    protected static ?string $title = 'Discovered keywords';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('keyword')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('keyword')->searchable()->wrap()->weight('medium'),
                TextColumn::make('volume')->label('Vol/mo')->numeric()->sortable()->placeholder('—')->alignEnd(),
                TextColumn::make('difficulty')->label('KD')->numeric()->sortable()->placeholder('—')->alignEnd(),
                TextColumn::make('intent')->badge()->toggleable()->color(fn (?string $s) => match ($s) {
                    'transactional' => 'danger', 'commercial' => 'warning', 'informational' => 'info', default => 'gray',
                }),
                TextColumn::make('cluster')->badge()->color('gray')->searchable(),
                TextColumn::make('role')->badge()->color(fn (?string $s) => $s === 'pillar' ? 'success' : 'gray'),
                TextColumn::make('funnel_stage')->label('Funnel')->badge()->color(fn (?string $s) => match ($s) {
                    'top' => 'info', 'middle' => 'warning', 'bottom' => 'danger', default => 'gray',
                }),
                TextColumn::make('opportunity')->label('Score')->numeric(decimalPlaces: 3)->sortable()->alignEnd(),
                ToggleColumn::make('chosen')->label('Use'),
            ])
            ->defaultSort('opportunity', 'desc')
            ->filters([
                SelectFilter::make('cluster')->options(fn () => $this->getOwnerRecord()->terms()
                    ->whereNotNull('cluster')->distinct()->orderBy('cluster')->pluck('cluster', 'cluster')->all()),
                SelectFilter::make('funnel_stage')->options(['top' => 'Top', 'middle' => 'Middle', 'bottom' => 'Bottom']),
                SelectFilter::make('role')->options(['pillar' => 'Pillar', 'spoke' => 'Spoke']),
                SelectFilter::make('source')->options(['seed' => 'Seed', 'related' => 'Related', 'question' => 'Question']),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkAction::make('choose')->label('Mark chosen')->icon('heroicon-o-check')
                    ->action(fn ($records) => $records->each->update(['chosen' => true]))->deselectRecordsAfterCompletion(),
                \Filament\Actions\BulkAction::make('skip')->label('Mark skipped')->icon('heroicon-o-x-mark')->color('gray')
                    ->action(fn ($records) => $records->each->update(['chosen' => false]))->deselectRecordsAfterCompletion(),
            ])
            ->paginated([25, 50, 100]);
    }
}
