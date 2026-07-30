<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffUserResource\Pages\CreateStaffUser;
use App\Filament\Resources\StaffUserResource\Pages\EditStaffUser;
use App\Filament\Resources\StaffUserResource\Pages\ListStaffUsers;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class StaffUserResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    protected static ?string $label = 'Staff user';

    protected static ?string $pluralLabel = 'Staff users';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('roles');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(10)
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                    ->required(fn (string $operation) => $operation === 'create'),
                Select::make('roles')
                    // relationship() drives both the options (id => name) and
                    // the exists-validation against roles.id. Do NOT also set
                    // ->options() keyed by name: that made the field submit
                    // role names, which then failed validation ("selected roles
                    // is invalid") because the pivot/relationship key is the id.
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
                Toggle::make('is_active')->default(true),
            ]),
            \Filament\Schemas\Components\Section::make('Public author profile (E-E-A-T)')
                ->description('Shown as the byline on blog posts this user writes, and emitted as Person schema for Google. Fill it for anyone who authors content — including the account that runs AI blog batches.')
                ->columns(2)
                ->schema([
                    TextInput::make('display_name')
                        ->label('Public display name')
                        ->placeholder('Terea Hub Editorial')
                        ->helperText('The byline readers see. Leave empty to use the account name. Keeping it different from the login name hides your admin identity.'),
                    \Filament\Forms\Components\Placeholder::make('public_url')
                        ->label('Public author URL (random, never the login name)')
                        ->content(fn (?\App\Models\User $record) => $record?->public_slug
                            ? $record->authorUrl()
                            : 'Generated automatically on save.'),
                    TextInput::make('job_title')
                        ->label('Job title')
                        ->placeholder('Heated-tobacco specialist at Terea Hub'),
                    \Filament\Forms\Components\FileUpload::make('avatar')
                        ->image()
                        ->avatar()
                        ->disk('public')
                        ->directory('avatars'),
                    \Filament\Forms\Components\Textarea::make('bio')
                        ->rows(2)
                        ->columnSpanFull()
                        ->placeholder('Tests and reviews every TEREA flavor sold in the UAE. Covering IQOS devices and heated tobacco since 2023.'),
                    \Filament\Forms\Components\Repeater::make('social_links')
                        ->label('Profile links (LinkedIn, X, Instagram…)')
                        ->simple(TextInput::make('url')->url()->placeholder('https://linkedin.com/in/…'))
                        ->default([])
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('roles.name')->badge()->separator(','),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('last_login_at')->dateTime()->sortable()->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')->relationship('roles', 'name'),
            ])
            ->recordActions([\Filament\Actions\EditAction::make(), \Filament\Actions\DeleteAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaffUsers::route('/'),
            'create' => CreateStaffUser::route('/create'),
            'edit' => EditStaffUser::route('/{record}/edit'),
        ];
    }
}
