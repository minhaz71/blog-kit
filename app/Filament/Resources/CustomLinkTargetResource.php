<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomLinkTargetResource\Pages\ManageCustomLinkTargets;
use App\Models\CustomLinkTarget;
use BackedEnum;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Admin-defined link destinations (homepage, landing pages) for the link
 * agent — targets that are not products/posts/categories. Each carries
 * several anchor phrases for natural variety and a site-wide max-links cap.
 */
class CustomLinkTargetResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;

    protected static ?string $model = CustomLinkTarget::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 26;

    protected static ?string $label = 'Custom link target';

    protected static ?string $pluralLabel = 'Custom link targets';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Destination')
                ->description('A page the link agent can link TO from product, category, and blog copy — for pages that are not themselves products/posts/categories (the homepage, a campaign landing page).')
                ->columns(2)
                ->schema([
                    TextInput::make('label')
                        ->required()
                        ->placeholder('Homepage')
                        ->helperText('Shown in the link agent and reports.'),
                    TextInput::make('url')
                        ->required()
                        ->placeholder('/')
                        ->helperText('Root-relative path, e.g. "/" or "/terea-guide".'),
                    TagsInput::make('anchor_phrases')
                        ->required()
                        ->columnSpanFull()
                        ->placeholder('TEREA Dubai')
                        ->helperText('The exact phrases that, when they appear naturally in copy, may be linked here. Add several for anchor variety — the agent links whichever genuinely appears; it never forces them in.'),
                    TextInput::make('weight')
                        ->numeric()->default(70)->minValue(1)->maxValue(100)
                        ->helperText('Match priority vs product/post targets (1-100).'),
                    TextInput::make('max_links')
                        ->numeric()->default(15)->minValue(1)
                        ->helperText('Site-wide cap: the agent stops suggesting once this many pages link here — prevents spammy over-linking of a base page.'),
                    Toggle::make('is_active')->default(true)->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->searchable()->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                TextColumn::make('url')->color('gray'),
                TextColumn::make('anchor_phrases')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state)
                    ->limitList(4),
                TextColumn::make('anchor_health')
                    ->label('In content')
                    ->badge()
                    ->state(function (CustomLinkTarget $record): string {
                        $total = count(array_filter(array_map('trim', (array) $record->anchor_phrases)));
                        $missing = count($record->unmatchedAnchorPhrases());

                        return match (true) {
                            $total === 0 => 'No anchors',
                            $missing === 0 => 'All '.$total.' found',
                            default => $missing.' of '.$total.' never match',
                        };
                    })
                    ->icon(fn (CustomLinkTarget $record) => $record->unmatchedAnchorPhrases() === []
                        ? 'heroicon-o-check-circle'
                        : 'heroicon-o-exclamation-triangle')
                    ->color(function (CustomLinkTarget $record): string {
                        $total = count(array_filter(array_map('trim', (array) $record->anchor_phrases)));
                        $missing = count($record->unmatchedAnchorPhrases());

                        return match (true) {
                            $total === 0 || $missing === $total => 'danger',
                            $missing === 0 => 'success',
                            default => 'warning',
                        };
                    })
                    ->tooltip(fn (CustomLinkTarget $record) => ($m = $record->unmatchedAnchorPhrases()) === []
                        ? 'Every anchor phrase appears in live content and can be linked.'
                        : 'Not found in any product/post/category content (will never be linked): '.implode(', ', $m)),
                TextColumn::make('max_links')->label('Cap')->alignCenter(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([\Filament\Actions\EditAction::make(), \Filament\Actions\DeleteAction::make()])
            ->emptyStateHeading('No custom targets yet')
            ->emptyStateDescription('Add the homepage or a landing page to let the agent link to it with keyword anchors.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageCustomLinkTargets::route('/')];
    }
}
