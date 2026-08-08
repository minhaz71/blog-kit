<?php

namespace App\Filament\Resources;

use App\Models\KeywordResearchRun;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Keyword Research — the front door to the content pipeline. Paste up to 100
 * seed keywords; the research layer pulls real demand data (DataForSEO, or free
 * Google Autocomplete), clusters it, stages it top/middle/bottom, and (on
 * "Create content plan") hands the winners to Blog Ideas → writer → linker →
 * category.
 */
class KeywordResearchResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;

    protected static ?string $model = KeywordResearchRun::class;

    protected static ?string $slug = 'keyword-research';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'Research';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Keyword Research';

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('New research')
                ->description('Paste your seed keywords — the system expands them into real related terms and questions, then clusters and stages them.')
                ->columns(2)
                ->schema([
                    TextInput::make('label')
                        ->label('Name')
                        ->placeholder('e.g. Japan travel — Q3')
                        ->columnSpanFull(),
                    Textarea::make('seeds')
                        ->label('Seed keywords (one per line, up to 100)')
                        ->rows(8)
                        ->required()
                        ->columnSpanFull()
                        ->helperText('These are your topics/keywords. The research expands around them.')
                        ->formatStateUsing(fn ($state) => is_array($state) ? implode("\n", $state) : (string) $state)
                        ->dehydrateStateUsing(fn ($state) => collect(preg_split('/\r?\n/', (string) $state))
                            ->map(fn ($s) => trim($s))->filter()->unique()->take(100)->values()->all()),
                    Select::make('provider')
                        ->options([
                            'auto' => 'Auto (DataForSEO → Google → LLM)',
                            'dataforseo' => 'DataForSEO only',
                            'google' => 'Free Google Autocomplete',
                            'llm' => 'LLM only',
                        ])->default('auto')->native(false),
                    // Multisite: research + plan FOR a connected spoke from the
                    // hub. Only shown when this install is a network hub.
                    Select::make('site_id')
                        ->label('Target site')
                        ->options(fn () => ['' => 'This site'] + \App\Models\ConnectedSite::query()
                            ->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->default('')
                        ->native(false)
                        ->visible(fn () => network_enabled() && is_network_hub())
                        ->helperText('Research, plan and generate for a connected site; content is pushed there over the key connection.'),
                    TextInput::make('target_country')->label('Target country (optional)')->placeholder('United Arab Emirates'),
                    TextInput::make('target_language')->label('Language code')->default('en'),
                    TextInput::make('location_code')->label('DataForSEO location code (optional)')->numeric()
                        ->helperText('e.g. 2840 US · 2826 UK · 2784 UAE · 2356 India.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->label('Name')->searchable()->weight('semibold')
                    ->description(fn (KeywordResearchRun $r) => collect((array) $r->seeds)->take(3)->implode(', ')),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'clustered' => 'success', 'planned' => 'success', 'researching' => 'warning',
                    'failed' => 'danger', default => 'gray',
                }),
                TextColumn::make('site.name')->label('Target')->badge()->color('gray')
                    ->placeholder('This site')->visible(fn () => network_enabled() && is_network_hub()),
                TextColumn::make('terms_count')->label('Terms')->alignCenter(),
                TextColumn::make('clusters_count')->label('Clusters')->alignCenter(),
                TextColumn::make('provider')->badge()->color('gray')->toggleable(),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (KeywordResearchRun $r) => KeywordResearchResource::getUrl('edit', ['record' => $r]));
    }

    public static function getRelations(): array
    {
        return [
            KeywordResearchResource\RelationManagers\TermsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => KeywordResearchResource\Pages\ListKeywordResearch::route('/'),
            'create' => KeywordResearchResource\Pages\CreateKeywordResearch::route('/create'),
            'edit' => KeywordResearchResource\Pages\EditKeywordResearch::route('/{record}/edit'),
        ];
    }
}
