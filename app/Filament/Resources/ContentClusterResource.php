<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContentClusterResource\Pages\EditContentCluster;
use App\Filament\Resources\ContentClusterResource\Pages\ListContentClusters;
use App\Models\ContentCluster;
use App\Models\Post;
use App\Services\Ai\ThumbnailService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The Content Plan: every topic cluster (pillar + spokes) the funnel builder
 * has produced, with its pillar, size and shared thumbnail identity. Clusters
 * are created automatically at publish; this is where an admin names the
 * pillar and sets the cluster's visual style.
 */
class ContentClusterResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;

    protected static ?string $model = ContentCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Content clusters';

    protected static ?string $recordTitleAttribute = 'name';

    /** Clusters are born from the planner — never hand-created here. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cluster')->columns(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('primary_keyword')->helperText('The pillar keyword this cluster targets.'),
                Select::make('pillar_post_id')
                    ->label('Pillar post (the hub)')
                    ->options(fn (?ContentCluster $record) => $record
                        ? Post::query()->where('content_cluster_id', $record->id)->pluck('title', 'id')
                        : [])
                    ->searchable()
                    ->native(false)
                    ->helperText('The broad guide every spoke links up to.'),
                Textarea::make('description')->rows(2)->columnSpanFull(),
            ]),
            Section::make('Thumbnail identity')
                ->description('A shared look so every article in this cluster reads as one visual set.')
                ->columns(2)
                ->schema([
                    Select::make('thumbnail_style')
                        ->label('Thumbnail style')
                        ->options(collect(ThumbnailService::STYLE_PRESETS)->map(fn ($v, $k) => ucfirst($k))->all())
                        ->native(false)
                        ->placeholder('Use the site default'),
                    TextInput::make('brand_hint')
                        ->label('Color / brand cue')
                        ->placeholder('e.g. teal and charcoal, soft neon')
                        ->helperText('Optional palette hint fed to the image generator.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                TextColumn::make('pillar.title')->label('Pillar')->placeholder('— none yet —')->limit(40)->toggleable(),
                TextColumn::make('posts_count')->counts('posts')->label('Articles')->badge()->color('info')->sortable(),
                TextColumn::make('spokes_count')->counts('spokes')->label('Spokes')->badge()->color('gray'),
                TextColumn::make('thumbnail_style')->label('Style')->badge()->placeholder('default')->toggleable(),
                TextColumn::make('primary_keyword')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([\Filament\Actions\EditAction::make()])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentClusters::route('/'),
            'edit' => EditContentCluster::route('/{record}/edit'),
        ];
    }
}
