<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContentBlockResource\Pages\CreateContentBlock;
use App\Filament\Resources\ContentBlockResource\Pages\EditContentBlock;
use App\Filament\Resources\ContentBlockResource\Pages\ListContentBlocks;
use App\Models\ContentBlock;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use App\Filament\Support\Editor;
use App\Filament\Support\ResourceActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class ContentBlockResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = ContentBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Block')->columns(2)->schema([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, ?string $state, Set $set) => $operation === 'create' ? $set('key', Str::slug($state ?? '', '_')) : null),
                TextInput::make('key')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('Short key used in {{block:key}} shortcodes. Auto-slugged from the name.'),
                Select::make('type')
                    ->options(ContentBlock::TYPES)
                    ->required()
                    ->default('html')
                    ->live()
                    ->native(false),
                Toggle::make('is_active')->default(true)->inline(false),
            ]),
            Editor::rich('body')
                ->required()
                ->columnSpanFull()
                ->helperText('For HTML/notice/CTA blocks this is the main body. For FAQ blocks it becomes the intro.'),
            Section::make('CTA button')
                ->visible(fn (Get $get) => $get('type') === 'cta')
                ->columns(2)
                ->schema([
                    TextInput::make('data.button_text'),
                    TextInput::make('data.button_url'),
                ]),
            Section::make('FAQ items')
                ->visible(fn (Get $get) => $get('type') === 'faq')
                ->schema([
                    Repeater::make('data.items')
                        ->schema([
                            TextInput::make('question')->required(),
                            Textarea::make('answer')->required()->rows(3),
                        ])
                        ->addActionLabel('Add FAQ item'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('key')->badge()->searchable(),
                TextColumn::make('type')->badge()->formatStateUsing(fn (string $state) => ContentBlock::TYPES[$state] ?? $state),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(ContentBlock::TYPES),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentBlocks::route('/'),
            'create' => CreateContentBlock::route('/create'),
            'edit' => EditContentBlock::route('/{record}/edit'),
        ];
    }
}
